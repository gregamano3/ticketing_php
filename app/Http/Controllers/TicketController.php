<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTicketRequest;
use App\Http\Requests\TriageTicketRequest;
use App\Http\Requests\UpdateTicketRequest;
use App\Models\CannedResponse;
use App\Models\Category;
use App\Models\Department;
use App\Models\Priority;
use App\Models\Status;
use App\Models\Tag;
use App\Models\Ticket;
use App\Models\User;
use App\Services\SlaService;
use App\Services\TicketService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketController extends Controller
{
    public const VIEWS = [
        'all' => 'All tickets',
        'triage' => 'Needs triage',
        'open' => 'Open tickets',
        'mine' => 'Assigned to me',
        'requested' => 'My requests',
        'unassigned' => 'Unassigned',
        'overdue' => 'Overdue',
        'watching' => 'Watching',
    ];

    private const SORTS = ['created_at', 'updated_at', 'due_resolution_at', 'priority', 'reference'];

    public function __construct(private TicketService $tickets, private SlaService $sla) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $views = $this->viewsFor($user);
        $view = array_key_exists($request->query('view'), $views) ? $request->query('view') : ($user->isStaff() ? 'open' : 'requested');

        $tickets = $this->filteredQuery($request, $view)
            ->with(['requester', 'assignee', 'priority', 'status', 'department', 'tags'])
            ->withCount('replies')
            ->paginate(20)
            ->withQueryString();

        return view('tickets.index', [
            'tickets' => $tickets,
            'view' => $view,
            'views' => $this->viewsFor($user),
            'sla' => $this->sla,
            ...$this->lookups(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorize('export', Ticket::class);

        $query = $this->filteredQuery($request, $request->query('view', 'all'))
            ->with(['requester', 'assignee', 'priority', 'status', 'department', 'category']);

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Reference', 'Subject', 'Status', 'Priority', 'Department', 'Category', 'Requester', 'Assignee', 'Created', 'First response', 'Resolved', 'Response breached', 'Resolution breached']);
            $query->chunk(500, function ($tickets) use ($out) {
                foreach ($tickets as $t) {
                    fputcsv($out, [
                        $t->reference, $t->subject, $t->status?->name, $t->priority?->name, $t->department?->name,
                        $t->category?->name, $t->requester?->name, $t->assignee?->name, $t->created_at?->toDateTimeString(),
                        $t->first_responded_at?->toDateTimeString(), $t->resolved_at?->toDateTimeString(),
                        $t->response_breached ? 'yes' : 'no', $t->resolution_breached ? 'yes' : 'no',
                    ]);
                }
            });
            fclose($out);
        }, 'tickets-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function create(Request $request): View
    {
        return view('tickets.create', $this->lookups());
    }

    public function store(StoreTicketRequest $request): RedirectResponse
    {
        $ticket = $this->tickets->create($request->validated(), $request->user(), $request->file('attachments', []));

        return redirect()->route('tickets.show', $ticket)->with('success', "Ticket {$ticket->reference} created.");
    }

    public function show(Request $request, Ticket $ticket): View
    {
        $this->authorize('view', $ticket);
        $user = $request->user();

        $ticket->load([
            'requester.department', 'assignee', 'priority', 'status', 'department', 'category', 'tags', 'watchers',
            'attachments.user', 'escalations.escalatedTo',
            'triager',
            'replies' => fn ($q) => $q->when(! $user->isStaff(), fn ($r) => $r->public())->with(['user', 'attachments'])->oldest(),
        ]);

        $activity = $user->isStaff()
            ? Activity::query()->where('subject_type', $ticket->getMorphClass())->where('subject_id', $ticket->id)
                ->with('causer')->latest()->limit(50)->get()
            : collect();

        return view('tickets.show', [
            'ticket' => $ticket,
            'activity' => $activity,
            'slaState' => $this->sla->state($ticket),
            'canned' => $user->isStaff() ? CannedResponse::availableTo($user)->orderBy('title')->get() : collect(),
            ...$this->lookups(),
        ]);
    }

    public function edit(Ticket $ticket): View
    {
        $this->authorize('update', $ticket);

        return view('tickets.edit', ['ticket' => $ticket->load(['tags', 'watchers']), ...$this->lookups()]);
    }

    public function update(UpdateTicketRequest $request, Ticket $ticket): RedirectResponse
    {
        $this->tickets->update($ticket, $request->validated(), $request->user());

        return redirect()->route('tickets.show', $ticket)->with('success', 'Ticket updated.');
    }

    public function destroy(Ticket $ticket): RedirectResponse
    {
        $this->authorize('delete', $ticket);
        $ticket->delete();

        return redirect()->route('tickets.index')->with('success', "Ticket {$ticket->reference} deleted.");
    }

    public function claim(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authorize('update', $ticket);
        $this->tickets->assign($ticket, $request->user(), $request->user());

        return back()->with('success', 'You are now assigned to this ticket.');
    }

    public function triage(TriageTicketRequest $request, Ticket $ticket): RedirectResponse
    {
        $this->tickets->triage($ticket, $request->validated(), $request->user());

        $assignee = $ticket->assignee?->name;

        return redirect()->route('tickets.show', $ticket)
            ->with('success', 'Ticket triaged'.($assignee ? " and assigned to {$assignee}." : '.'));
    }

    public function sendBackToTriage(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authorize('sendBackToTriage', $ticket);
        $reason = $request->validate(['reason' => ['required', 'string', 'max:1000']])['reason'];

        $this->tickets->sendBackToTriage($ticket, $reason, $request->user());

        return redirect()->route('tickets.index', ['view' => 'open'])->with('success', "Ticket {$ticket->reference} was sent back to triage.");
    }

    public function toggleWatch(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authorize('view', $ticket);
        $result = $ticket->watchers()->toggle($request->user()->id);

        return back()->with('success', $result['attached'] ? 'You are now watching this ticket.' : 'You stopped watching this ticket.');
    }

    /** @return array<string, string> */
    private function viewsFor(User $user): array
    {
        $views = $user->isStaff() ? self::VIEWS : array_intersect_key(self::VIEWS, array_flip(['requested', 'watching', 'all']));

        if (! $user->canTriage()) {
            unset($views['triage']);
        }

        return $views;
    }

    private function filteredQuery(Request $request, string $view): Builder
    {
        $user = $request->user();

        $query = Ticket::query()->visibleTo($user)->select('tickets.*');

        match ($view) {
            'triage' => $query->needsTriage(),
            'open' => $query->open(),
            'mine' => $query->open()->where('assignee_id', $user->id),
            'requested' => $query->where('requester_id', $user->id),
            'unassigned' => $query->open()->unassigned(),
            'overdue' => $query->overdue(),
            'watching' => $query->whereHas('watchers', fn ($w) => $w->where('users.id', $user->id)),
            default => null,
        };

        $query->search($request->query('q'))
            ->when($request->query('status'), fn ($q, $v) => $q->where('status_id', $v))
            ->when($request->query('priority'), fn ($q, $v) => $q->where('priority_id', $v))
            ->when($request->query('department'), fn ($q, $v) => $q->where('department_id', $v))
            ->when($request->query('category'), fn ($q, $v) => $q->where('category_id', $v))
            ->when($request->query('assignee'), fn ($q, $v) => $v === 'none' ? $q->whereNull('assignee_id') : $q->where('assignee_id', $v))
            ->when($request->query('tag'), fn ($q, $v) => $q->whereHas('tags', fn ($t) => $t->where('tags.id', $v)))
            ->when($request->query('from'), fn ($q, $v) => $q->whereDate('tickets.created_at', '>=', $v))
            ->when($request->query('to'), fn ($q, $v) => $q->whereDate('tickets.created_at', '<=', $v))
            ->when($request->boolean('breached'), fn ($q) => $q->where(fn ($b) => $b->where('response_breached', true)->orWhere('resolution_breached', true)));

        $sort = in_array($request->query('sort'), self::SORTS, true) ? $request->query('sort') : 'updated_at';
        $dir = $request->query('dir') === 'asc' ? 'asc' : 'desc';

        if ($sort === 'priority') {
            $query->join('priorities', 'priorities.id', '=', 'tickets.priority_id')->orderBy('priorities.level', $dir);
        } elseif ($sort === 'due_resolution_at') {
            $query->orderByRaw('due_resolution_at '.$dir.' NULLS LAST');
        } else {
            $query->orderBy('tickets.'.$sort, $dir);
        }

        return $query;
    }

    /** Option lists shared by the ticket forms and filters. */
    private function lookups(): array
    {
        return [
            'statuses' => Status::ordered()->get(),
            'priorities' => Priority::orderBy('level')->get(),
            'departments' => Department::orderBy('name')->get(),
            'categories' => Category::active()->with('parent')->orderBy('name')->get(),
            'tags' => Tag::orderBy('name')->get(),
            'agents' => User::agents()->active()->with('department')->orderBy('name')->get(),
            'users' => User::active()->orderBy('name')->get(['id', 'name', 'email']),
        ];
    }
}
