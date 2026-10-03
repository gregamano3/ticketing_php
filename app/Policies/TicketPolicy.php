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
            || ($user->isAgent() && $user->department_id && $ticket->department_id === $user->department_id)
            || $ticket->watchers()->whereKey($user->id)->exists();
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
