<?php

namespace App\Notifications;

class TicketCreatedNotification extends TicketNotification
{
    protected function subjectLine(): string
    {
        return 'New ticket: '.$this->ticket->subject;
    }

    protected function message(): string
    {
        $this->ticket->loadMissing('requester');

        return "New ticket {$this->ticket->reference} was opened by {$this->ticket->requester?->name}.";
    }

    protected function icon(): string
    {
        return 'bi bi-plus-circle';
    }
}
