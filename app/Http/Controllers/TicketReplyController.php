<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReplyRequest;
use App\Models\Ticket;
use App\Services\TicketService;
use Illuminate\Http\RedirectResponse;

class TicketReplyController extends Controller
{
    public function store(StoreReplyRequest $request, Ticket $ticket, TicketService $tickets): RedirectResponse
    {
        $reply = $tickets->reply($ticket, $request->validated(), $request->user(), $request->file('attachments', []));

        return redirect()->to(route('tickets.show', $ticket).'#reply-'.$reply->id)
            ->with('success', $reply->is_internal ? 'Internal note added.' : 'Reply posted.');
    }
}
