<?php

namespace App\Events;

use App\Models\TicketReply;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TicketReplied
{
    use Dispatchable, SerializesModels;

    public function __construct(public TicketReply $reply, public User $actor) {}
}
