<?php

namespace App\Notifications;

use App\Models\TicketReply;
use Illuminate\Support\Str;

class TicketRepliedNotification extends TicketNotification
{
    public function __construct(public TicketReply $reply)
    {
        parent::__construct($reply->ticket);
    }

    protected function subjectLine(): string
    {
        return $this->reply->is_internal ? 'New internal note' : 'New reply';
    }

    protected function message(): string
    {
        $this->reply->loadMissing('user');
        $kind = $this->reply->is_internal ? 'added an internal note to' : 'replied to';

        return "{$this->reply->user?->name} {$kind} ticket {$this->ticket->reference}.";
    }

    protected function details(): array
    {
        return ['> '.Str::limit(strip_tags($this->reply->body), 500)];
    }

    protected function icon(): string
    {
        return $this->reply->is_internal ? 'bi bi-lock' : 'bi bi-chat-left-text';
    }

    protected function color(): string
    {
        return $this->reply->is_internal ? 'warning' : 'success';
    }

    protected function url(): string
    {
        return route('tickets.show', $this->ticket).'#reply-'.$this->reply->id;
    }
}
