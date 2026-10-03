<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;

class TicketPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    /** Mirrors Ticket::scopeVisibleTo(). */
    public function view(User $user, Ticket $ticket): bool
    {
        return $ticket->requester_id === $user->id
            || $ticket->assignee_id === $user->id
            || $ticket->triaged_by === $user->id
            || ($user->isAgent() && $user->department_id && $ticket->department_id === $user->department_id)
            || ($ticket->triaged_at === null && $user->canTriage())
            || $ticket->watchers()->whereKey($user->id)->exists();
    }

    /** Triagers, and staff already working the ticket, may complete its triage. */
    public function triage(User $user, Ticket $ticket): bool
    {
        return $ticket->needsTriage() && ($user->canTriage() || $this->update($user, $ticket));
    }

    public function sendBackToTriage(User $user, Ticket $ticket): bool
    {
        return ! $ticket->needsTriage() && $ticket->isOpen() && $this->update($user, $ticket);
    }

    public function create(User $user): bool
    {
        return true;
    }

    /** Edit properties: status, priority, assignee, department, tags, watchers. */
    public function update(User $user, Ticket $ticket): bool
    {
        return $user->isStaff() && $this->view($user, $ticket);
    }

    public function reply(User $user, Ticket $ticket): bool
    {
        return $this->view($user, $ticket);
    }

    public function addInternalNote(User $user, Ticket $ticket): bool
    {
        return $user->isStaff() && $this->view($user, $ticket);
    }

    public function delete(User $user, Ticket $ticket): bool
    {
        return false; // admins only (Gate::before)
    }

    public function export(User $user): bool
    {
        return $user->isStaff();
    }
}
