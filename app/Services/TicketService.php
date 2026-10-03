<?php

namespace App\Services;

use App\Events\TicketAssigned;
use App\Events\TicketCreated;
use App\Events\TicketReplied;
use App\Events\TicketStatusChanged;
use App\Models\Attachment;
use App\Models\Priority;
use App\Models\Status;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TicketService
{
    public function __construct(private SlaService $sla) {}

    /**
     * Open a new ticket.
     *
     * @param  array<string, mixed>  $data
     * @param  UploadedFile[]  $files
     */
    public function create(array $data, User $actor, array $files = []): Ticket
    {
        $ticket = DB::transaction(function () use ($data, $actor, $files) {
            $ticket = new Ticket(Arr::only($data, [
                'subject', 'description', 'department_id', 'category_id', 'priority_id', 'source',
            ]));

            // Only staff may open tickets on behalf of someone else or pick an assignee.
            $ticket->requester_id = $actor->isStaff() && ! empty($data['requester_id']) ? $data['requester_id'] : $actor->id;
            $ticket->assignee_id = $actor->isStaff() ? ($data['assignee_id'] ?? null) : null;
            $ticket->priority_id ??= Priority::default()?->id;
            $ticket->status_id = Status::default()->id;
            $ticket->reference = 'TMP-'.Str::uuid(); // replaced once the id is known
            $ticket->created_at = now();
            $this->sla->applyTargets($ticket, $ticket->created_at);
            $ticket->save();

            $ticket->reference = config('helpdesk.reference_prefix').str_pad((string) $ticket->id, 6, '0', STR_PAD_LEFT);
            $ticket->saveQuietly();

            if (! $ticket->assignee_id && config('helpdesk.auto_assign')) {
                $this->autoAssign($ticket);
            }

            if (! empty($data['tags'])) {
                $ticket->tags()->sync($data['tags']);
            }
            if (! empty($data['watchers'])) {
                $ticket->watchers()->sync(array_diff($data['watchers'], [$ticket->requester_id]));
            }

            foreach ($files as $file) {
                Attachment::storeFor($ticket, $file, $actor);
            }

            if ($ticket->assignee_id) {
                $ticket->assignee->forceFill(['last_assigned_at' => now()])->saveQuietly();
            }

            return $ticket;
        });

        TicketCreated::dispatch($ticket, $actor);

        return $ticket;
    }

    /**
     * Update ticket properties, firing assignment/status side effects.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Ticket $ticket, array $data, User $actor): Ticket
    {
        $previousStatus = $ticket->status;
        $previousAssigneeId = $ticket->assignee_id;

        DB::transaction(function () use ($ticket, $data) {
            $ticket->fill(Arr::only($data, [
                'subject', 'description', 'department_id', 'category_id', 'priority_id', 'status_id', 'assignee_id',
            ]));

            if ($ticket->isDirty('priority_id')) {
                $ticket->load('priority');
                $this->sla->applyTargets($ticket);
            }

            if ($ticket->isDirty('status_id')) {
                $this->applyStatusSideEffects($ticket);
            }

            $ticket->save();

            if (array_key_exists('tags', $data)) {
                $ticket->tags()->sync($data['tags'] ?? []);
            }
            if (array_key_exists('watchers', $data)) {
                $ticket->watchers()->sync(array_diff($data['watchers'] ?? [], [$ticket->requester_id]));
            }
        });

        $this->dispatchChangeEvents($ticket, $actor, $previousStatus, $previousAssigneeId);

        return $ticket;
    }

    public function assign(Ticket $ticket, ?User $assignee, User $actor): Ticket
    {
        return $this->update($ticket, ['assignee_id' => $assignee?->id], $actor);
    }

    public function changeStatus(Ticket $ticket, Status $status, User $actor): Ticket
    {
        return $this->update($ticket, ['status_id' => $status->id], $actor);
    }

    /**
     * Add a reply or internal note. Staff replies may also change the status;
     * a requester replying to a resolved ticket reopens it.
     *
     * @param  array<string, mixed>  $data
     * @param  UploadedFile[]  $files
     */
    public function reply(Ticket $ticket, array $data, User $actor, array $files = []): TicketReply
    {
        $isInternal = $actor->isStaff() && ! empty($data['is_internal']);

        $reply = DB::transaction(function () use ($ticket, $data, $actor, $files, $isInternal) {
            $reply = $ticket->replies()->create([
                'user_id' => $actor->id,
                'body' => $data['body'],
                'is_internal' => $isInternal,
            ]);

            foreach ($files as $file) {
                Attachment::storeFor($reply, $file, $actor);
            }

            // First public response from someone other than the requester stops the response SLA clock.
            if (! $isInternal && $actor->isStaff() && $actor->id !== $ticket->requester_id && ! $ticket->first_responded_at) {
                $ticket->first_responded_at = now();
                $ticket->saveQuietly();
            }

            activity('ticket')
                ->performedOn($ticket)
                ->causedBy($actor)
                ->event($isInternal ? 'noted' : 'replied')
                ->withProperties(['reply_id' => $reply->id])
                ->log($isInternal ? 'added an internal note' : 'replied');

            return $reply;
        });

        TicketReplied::dispatch($reply, $actor);

        $newStatusId = $actor->isStaff() ? ($data['status_id'] ?? null) : null;

        if (! $newStatusId && $actor->id === $ticket->requester_id && ! $ticket->isOpen()) {
            $newStatusId = Status::default()->id;
        }

        if ($newStatusId && (int) $newStatusId !== $ticket->status_id) {
            $this->update($ticket, ['status_id' => $newStatusId], $actor);
        }

        return $reply;
    }

    /** Assign to the active department agent with the fewest open tickets (oldest assignment breaks ties). */
    public function autoAssign(Ticket $ticket): ?User
    {
        if (! $ticket->department_id) {
            return null;
        }

        $agent = User::role('agent')->active()
            ->where('department_id', $ticket->department_id)
            ->withCount(['assignedTickets as open_tickets_count' => fn ($q) => $q->open()])
            ->orderBy('open_tickets_count')
            ->orderByRaw('last_assigned_at ASC NULLS FIRST')
            ->first();

        if ($agent) {
            $ticket->assignee_id = $agent->id;
            $ticket->saveQuietly();
            $agent->forceFill(['last_assigned_at' => now()])->saveQuietly();
        }

        return $agent;
    }

    private function applyStatusSideEffects(Ticket $ticket): void
    {
        $ticket->load('status');
        $status = $ticket->status;

        $ticket->resolved_at = $status->is_resolved ? ($ticket->resolved_at ?? now()) : null;
        $ticket->closed_at = $status->is_closed ? ($ticket->closed_at ?? now()) : null;

        $this->sla->handleStatusChange($ticket);
    }

    private function dispatchChangeEvents(Ticket $ticket, User $actor, ?Status $previousStatus, ?int $previousAssigneeId): void
    {
        if ($ticket->assignee_id !== $previousAssigneeId) {
            if ($ticket->assignee) {
                $ticket->assignee->forceFill(['last_assigned_at' => now()])->saveQuietly();
            }
            TicketAssigned::dispatch($ticket->load('assignee'), $actor, $previousAssigneeId);
        }

        if ($ticket->status_id !== $previousStatus?->id) {
            TicketStatusChanged::dispatch($ticket->load('status'), $actor, $previousStatus);
        }
    }
}
