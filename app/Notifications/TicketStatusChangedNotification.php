<?php

namespace App\Notifications;

use App\Models\Status;
use App\Models\Ticket;

class TicketStatusChangedNotification extends TicketNotification
{
    public function __construct(Ticket $ticket, public ?Status $previousStatus)
    {
        parent::__construct($ticket);
    }

    protected function subjectLine(): string
    {
        return 'Status changed to '.$this->ticket->status?->name;
    }

    protected function message(): string
    {
        $from = $this->previousStatus?->name ?? '—';

        return "Ticket {$this->ticket->reference} moved from {$from} to {$this->ticket->status?->name}.";
    }

    protected function icon(): string
    {
        return 'bi bi-arrow-repeat';
    }

    protected function color(): string
    {
        return 'secondary';
    }
}
