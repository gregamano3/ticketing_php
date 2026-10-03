<?php

namespace App\Notifications;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Base class for ticket notifications: queued, delivered by mail and stored
 * in the database for the navbar bell.
 */
abstract class TicketNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Ticket $ticket) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /** One-line summary used for the bell dropdown and the mail intro. */
    abstract protected function message(): string;

    abstract protected function subjectLine(): string;

    protected function icon(): string
    {
        return 'bi bi-ticket-perforated';
    }

    protected function color(): string
    {
        return 'primary';
    }

    protected function url(): string
    {
        return route('tickets.show', $this->ticket);
    }

    /** Extra lines appended to the mail body. */
    protected function details(): array
    {
        return [];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $this->ticket->loadMissing(['priority', 'status']);

        $mail = (new MailMessage)
            ->subject("[{$this->ticket->reference}] {$this->subjectLine()}")
            ->greeting("Hello {$notifiable->name},")
            ->line($this->message());

        foreach ($this->details() as $line) {
            $mail->line($line);
        }

        return $mail
            ->line("**Subject:** {$this->ticket->subject}")
            ->line("**Priority:** {$this->ticket->priority?->name} · **Status:** {$this->ticket->status?->name}")
            ->action('View ticket', $this->url());
    }

    public function toArray(object $notifiable): array
    {
        return [
            'ticket_id' => $this->ticket->id,
            'reference' => $this->ticket->reference,
            'message' => $this->message(),
            'icon' => $this->icon(),
            'color' => $this->color(),
            'url' => $this->url(),
        ];
    }
}
