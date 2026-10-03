<?php

namespace App\Notifications;

/** Confirmation sent to the requester when their ticket is logged. */
class TicketReceivedNotification extends TicketNotification
{
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    protected function subjectLine(): string
    {
        return 'We received your request';
    }

    protected function message(): string
    {
        return "Your ticket {$this->ticket->reference} has been logged. We'll keep you updated by email.";
    }

    protected function details(): array
    {
        $this->ticket->loadMissing('priority');

        return $this->ticket->priority
            ? ['Expected first response within '.now()->addMinutes($this->ticket->priority->response_minutes)->diffForHumans(syntax: true, parts: 2).'.']
            : [];
    }
}
