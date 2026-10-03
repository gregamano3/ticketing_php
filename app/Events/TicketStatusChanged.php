<?php

namespace App\Events;

use App\Models\Status;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TicketStatusChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(public Ticket $ticket, public ?User $actor, public ?Status $previousStatus) {}
}
