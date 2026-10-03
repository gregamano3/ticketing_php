<?php

namespace App\Listeners;

use App\Events\TicketAssigned;
use App\Events\TicketCreated;
use App\Events\TicketReplied;
use App\Events\TicketStatusChanged;
use App\Models\User;
use App\Notifications\TicketAssignedNotification;
use App\Notifications\TicketCreatedNotification;
use App\Notifications\TicketReceivedNotification;
use App\Notifications\TicketRepliedNotification;
use App\Notifications\TicketStatusChangedNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/** Decides who hears about each ticket event. The actor is never notified of their own action. */
class SendTicketNotifications
{
    public function handleTicketCreated(TicketCreated $event): void
    {
        $ticket = $event->ticket->loadMissing(['requester', 'assignee', 'watchers']);

        $ticket->requester?->notify(new TicketReceivedNotification($ticket));

        // Staff who should pick it up: the assignee, else the department's agents,
        // else (no department chosen) the admins, who triage it.
        $staff = match (true) {
            (bool) $ticket->assignee => collect([$ticket->assignee]),
            (bool) $ticket->department_id => User::role('agent')->active()->where('department_id', $ticket->department_id)->get(),
            default => collect(),
        };

        if ($staff->isEmpty()) {
            $staff = User::role('admin')->active()->get();
        }

        Notification::send(
            $this->except($staff->merge($ticket->watchers), $event->actor),
            new TicketCreatedNotification($ticket)
        );
    }

    public function handleTicketAssigned(TicketAssigned $event): void
    {
        $assignee = $event->ticket->assignee;

        if ($assignee && $assignee->id !== $event->actor?->id) {
            $assignee->notify(new TicketAssignedNotification($event->ticket));
        }
    }

    public function handleTicketReplied(TicketReplied $event): void
    {
        $reply = $event->reply;
        $participants = $reply->ticket->loadMissing(['requester', 'assignee', 'watchers'])->participants();

        // Internal notes are for staff only.
        if ($reply->is_internal) {
            $participants = $participants->filter(fn (User $u) => $u->isStaff());
        }

        Notification::send($this->except($participants, $event->actor), new TicketRepliedNotification($reply));
    }

    public function handleTicketStatusChanged(TicketStatusChanged $event): void
    {
        $participants = $event->ticket->loadMissing(['requester', 'assignee', 'watchers'])->participants();

        Notification::send(
            $this->except($participants, $event->actor),
            new TicketStatusChangedNotification($event->ticket, $event->previousStatus)
        );
    }

    private function except(Collection $users, ?User $actor): Collection
    {
        return $users->filter()
            ->unique('id')
            ->reject(fn (User $u) => $u->id === $actor?->id || ! $u->is_active)
            ->values();
    }
}
