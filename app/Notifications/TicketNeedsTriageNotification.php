<?php

namespace App\Notifications;

use App\Models\Ticket;

class TicketNeedsTriageNotification extends TicketNotification
{
    public function __construct(Ticket $ticket, public string $reason)
    {
        parent::__construct($ticket);
    }

    protected function subjectLine(): string
    {
        return 'Sent back to triage';
    }

    protected function message(): string
    {
        return "Ticket {$this->ticket->reference} was sent back to triage.";
    }

    protected function details(): array
    {
        return ["Reason: {$this->reason}"];
    }

    protected function icon(): string
    {
        return 'bi bi-signpost-split';
    }

    protected function color(): string
    {
        return 'warning';
    }
}
