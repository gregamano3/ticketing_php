<?php

namespace App\Http\Controllers;

use App\Models\Status;
use App\Models\Ticket;
use App\Services\SlaService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Spatie\Activitylog\Models\Activity;

class DashboardController extends Controller
{
    public function __invoke(Request $request, SlaService $sla): View
    {
        $user = $request->user();
        $base = fn () => Ticket::query()->visibleTo($user);

        $stats = [
            'open' => $base()->open()->count(),
            'mine' => $base()->open()->where('assignee_id', $user->id)->count(),
            'requested' => $base()->open()->where('requester_id', $user->id)->count(),
            'unassigned' => $base()->open()->unassigned()->count(),
            'overdue' => $base()->overdue()->count(),
            'resolved_today' => $base()->whereDate('resolved_at', today())->count(),
        ];

        $byStatus = Status::ordered()->get()->map(fn (Status $s) => [
            'label' => $s->name,
            'color' => $s->color,
            'count' => $base()->where('status_id', $s->id)->count(),
        ]);

        // Created vs resolved over the last 30 days (one query each, grouped by day).
        $days = collect(range(29, 0))->map(fn ($i) => today()->subDays($i)->toDateString());
        $created = $base()->where('created_at', '>=', today()->subDays(29))
            ->select(DB::raw('DATE(created_at) as day'), DB::raw('COUNT(*) as c'))->groupBy('day')->pluck('c', 'day');
        $resolved = $base()->where('resolved_at', '>=', today()->subDays(29))
            ->select(DB::raw('DATE(resolved_at) as day'), DB::raw('COUNT(*) as c'))->groupBy('day')->pluck('c', 'day');

        $trend = [
            'labels' => $days->map(fn ($d) => Carbon::parse($d)->format('M j'))->values(),
            'created' => $days->map(fn ($d) => (int) ($created[$d] ?? 0))->values(),
            'resolved' => $days->map(fn ($d) => (int) ($resolved[$d] ?? 0))->values(),
        ];

        $attention = $base()->open()
            ->with(['priority', 'status', 'assignee'])
            ->when($user->isStaff(), fn ($q) => $q->where(fn ($w) => $w->where('assignee_id', $user->id)->orWhereNull('assignee_id')))
            ->orderByRaw('due_resolution_at ASC NULLS LAST')
            ->limit(8)
            ->get();

        $recentActivity = $user->isStaff()
            ? Activity::query()->where('log_name', 'ticket')
                ->whereIn('subject_id', $base()->select('id'))
                ->with(['causer', 'subject'])->latest()->limit(10)->get()
            : collect();

        return view('dashboard', compact('stats', 'byStatus', 'trend', 'attention', 'recentActivity', 'sla'));
    }
}
