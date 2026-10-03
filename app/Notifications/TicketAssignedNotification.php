<?php

namespace App\Notifications;

class TicketAssignedNotification extends TicketNotification
{
    protected function subjectLine(): string
    {
        return 'Ticket assigned to you';
    }

    protected function message(): string
    {
        return "Ticket {$this->ticket->reference} has been assigned to you.";
    }

    protected function icon(): string
    {
        return 'bi bi-person-check';
    }

    protected function color(): string
    {
        return 'info';
    }
}
