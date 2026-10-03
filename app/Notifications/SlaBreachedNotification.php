<?php

namespace App\Notifications;

use App\Models\Ticket;

class SlaBreachedNotification extends TicketNotification
{
    public function __construct(Ticket $ticket, public string $type, public int $level)
    {
        parent::__construct($ticket);
    }

    protected function subjectLine(): string
    {
        return "SLA {$this->type} breached (escalation level {$this->level})";
    }

    protected function message(): string
    {
        return "Ticket {$this->ticket->reference} breached its {$this->type} SLA and was escalated to level {$this->level}.";
    }

    protected function details(): array
    {
        $due = match ($this->type) {
            'response' => $this->ticket->due_response_at,
            'triage' => $this->ticket->created_at->copy()->addMinutes((int) config('helpdesk.triage_minutes')),
            default => $this->ticket->due_resolution_at,
        };

        return $due ? ["It was due {$due->diffForHumans()} ({$due->toDayDateTimeString()})."] : [];
    }

    protected function icon(): string
    {
        return 'bi bi-exclamation-triangle';
    }

    protected function color(): string
    {
        return 'danger';
    }
}
