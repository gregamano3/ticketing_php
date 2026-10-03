<?php

namespace App\Services;

use App\Models\SlaEscalation;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\SlaBreachedNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

class SlaService
{
    /** Set the response and resolution due dates from the ticket's priority. */
    public function applyTargets(Ticket $ticket, ?Carbon $from = null): void
    {
        $from ??= $ticket->created_at ?? now();
        $priority = $ticket->priority;

        if (! $priority) {
            return;
        }

        if (! $ticket->first_responded_at) {
            $ticket->due_response_at = $from->copy()->addMinutes($priority->response_minutes);
            $ticket->response_breached = false;
        }

        $ticket->due_resolution_at = $from->copy()->addMinutes($priority->resolution_minutes);
        $ticket->resolution_breached = false;
    }

    /**
     * Pause or resume the SLA clock when the ticket enters or leaves a status
     * that pauses it (e.g. "Pending" while waiting on the requester). On
     * resume, the due dates move forward by the time spent paused.
     */
    public function handleStatusChange(Ticket $ticket): void
    {
        $status = $ticket->status;

        if ($status?->pauses_sla && ! $ticket->sla_paused_at) {
            $ticket->sla_paused_at = now();

            return;
        }

        if (! $status?->pauses_sla && $ticket->sla_paused_at) {
            $pausedSeconds = (int) $ticket->sla_paused_at->diffInSeconds(now());

            if ($ticket->due_response_at && ! $ticket->first_responded_at) {
                $ticket->due_response_at = $ticket->due_response_at->copy()->addSeconds($pausedSeconds);
            }
            if ($ticket->due_resolution_at) {
                $ticket->due_resolution_at = $ticket->due_resolution_at->copy()->addSeconds($pausedSeconds);
            }

            $ticket->sla_paused_at = null;
        }
    }

    /**
     * Flag breached tickets and escalate them. Level 1 notifies the assignee
     * and department lead; level 2 (after a delay) notifies administrators.
     *
     * @return array{response: int, resolution: int, triage: int, escalated: int}
     */
    public function checkBreaches(): array
    {
        $stats = ['response' => 0, 'resolution' => 0, 'triage' => 0, 'escalated' => 0];

        $stats['triage'] = $this->checkTriage();
        $stats['escalated'] += $stats['triage'];

        Ticket::query()
            ->open()
            ->whereNull('sla_paused_at')
            ->with(['assignee', 'department.lead', 'priority', 'status', 'escalations'])
            ->where(function ($q) {
                $q->where(fn ($r) => $r->whereNull('first_responded_at')->where('due_response_at', '<', now()))
                    ->orWhere('due_resolution_at', '<', now());
            })
            ->chunkById(100, function ($tickets) use (&$stats) {
                foreach ($tickets as $ticket) {
                    $this->evaluate($ticket, $stats);
                }
            });

        return $stats;
    }

    /**
     * Untriaged tickets waiting longer than the triage target are escalated
     * once to the triagers.
     */
    private function checkTriage(): int
    {
        $count = 0;
        $triagers = User::triagers()->get();

        Ticket::query()
            ->needsTriage()
            ->where('created_at', '<', now()->subMinutes((int) config('helpdesk.triage_minutes')))
            ->whereDoesntHave('escalations', fn ($q) => $q->where('type', 'triage'))
            ->chunkById(100, function ($tickets) use ($triagers, &$count) {
                foreach ($tickets as $ticket) {
                    foreach ($triagers->whenEmpty(fn () => collect([null])) as $user) {
                        SlaEscalation::create([
                            'ticket_id' => $ticket->id,
                            'type' => 'triage',
                            'level' => 1,
                            'escalated_to' => $user?->id,
                            'triggered_at' => now(),
                        ]);
                    }

                    activity('ticket')->performedOn($ticket)->event('escalated')
                        ->withProperties(['type' => 'triage', 'level' => 1])
                        ->log('waiting for triage too long — escalated to triagers');

                    Notification::send($triagers, new SlaBreachedNotification($ticket, 'triage', 1));
                    $count++;
                }
            });

        return $count;
    }

    private function evaluate(Ticket $ticket, array &$stats): void
    {
        $newBreach = null;

        if (! $ticket->response_breached && ! $ticket->first_responded_at && $ticket->due_response_at?->isPast()) {
            $ticket->response_breached = true;
            $newBreach = 'response';
            $stats['response']++;
        }

        if (! $ticket->resolution_breached && $ticket->due_resolution_at?->isPast()) {
            $ticket->resolution_breached = true;
            $newBreach = 'resolution';
            $stats['resolution']++;
        }

        if ($newBreach && $ticket->escalation_level < 1) {
            $this->escalate($ticket, 1, $newBreach);
            $stats['escalated']++;
        } elseif ($ticket->escalation_level === 1 && $this->dueForLevelTwo($ticket)) {
            $this->escalate($ticket, 2, $ticket->resolution_breached ? 'resolution' : 'response');
            $stats['escalated']++;
        }

        if ($ticket->isDirty()) {
            $ticket->save();
        }
    }

    private function dueForLevelTwo(Ticket $ticket): bool
    {
        $last = $ticket->escalations->where('level', 1)->where('type', '!=', 'triage')->sortByDesc('triggered_at')->first();

        return $last && $last->triggered_at->addMinutes((int) config('helpdesk.escalation_level2_after_minutes'))->isPast();
    }

    private function escalate(Ticket $ticket, int $level, string $type): void
    {
        $recipients = $this->escalationRecipients($ticket, $level);

        $ticket->escalation_level = $level;

        foreach ($recipients->whenEmpty(fn () => collect([null])) as $user) {
            SlaEscalation::create([
                'ticket_id' => $ticket->id,
                'type' => $type,
                'level' => $level,
                'escalated_to' => $user?->id,
                'triggered_at' => now(),
            ]);
        }

        activity('ticket')
            ->performedOn($ticket)
            ->withProperties(['type' => $type, 'level' => $level])
            ->event('escalated')
            ->log("SLA {$type} breached — escalated to level {$level}");

        Notification::send($recipients, new SlaBreachedNotification($ticket, $type, $level));
    }

    /** @return Collection<int, User> */
    private function escalationRecipients(Ticket $ticket, int $level): Collection
    {
        $users = $level === 1
            ? collect([$ticket->assignee, $ticket->department?->lead])
            : User::role('admin')->active()->get();

        // Nobody to escalate to at level 1 (unassigned, no lead): go to admins.
        if ($users->filter()->isEmpty()) {
            $users = User::role('admin')->active()->get();
        }

        return $users->filter()->unique('id')->values();
    }

    /** Human-friendly SLA state for badges: ok | at_risk | breached | paused | met. */
    public function state(Ticket $ticket): string
    {
        if (! $ticket->isOpen()) {
            return $ticket->resolution_breached ? 'breached' : 'met';
        }
        if ($ticket->sla_paused_at) {
            return 'paused';
        }
        if ($ticket->isBreached() || $ticket->due_resolution_at?->isPast()) {
            return 'breached';
        }
        if ($ticket->due_resolution_at && $ticket->due_resolution_at->lte(now()->addMinutes((int) config('helpdesk.at_risk_minutes')))) {
            return 'at_risk';
        }

        return 'ok';
    }
}
