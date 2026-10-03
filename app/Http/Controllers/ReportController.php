<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        [$from, $to] = $this->range($request);

        return view('reports.index', [
            'from' => $from,
            'to' => $to,
            'summary' => $this->summary($from, $to),
            'byStatus' => $this->volumeBy('statuses', 'status_id', $from, $to),
            'byPriority' => $this->volumeBy('priorities', 'priority_id', $from, $to),
            'byDepartment' => $this->volumeBy('departments', 'department_id', $from, $to),
            'byCategory' => $this->volumeBy('categories', 'category_id', $from, $to),
            'agents' => $this->agentPerformance($from, $to),
        ]);
    }

    public function export(Request $request, string $report): StreamedResponse
    {
        [$from, $to] = $this->range($request);

        $rows = match ($report) {
            'agents' => $this->agentPerformance($from, $to)->map(fn ($a) => [
                'Agent' => $a->name, 'Assigned' => $a->assigned, 'Resolved' => $a->resolved, 'Open now' => $a->open_now,
                'Avg first response (min)' => $a->avg_response, 'Avg resolution (min)' => $a->avg_resolution, 'SLA breaches' => $a->breaches,
            ]),
            'status', 'priority', 'department', 'category' => $this->volumeBy(
                ['status' => 'statuses', 'priority' => 'priorities', 'department' => 'departments', 'category' => 'categories'][$report],
                $report.'_id', $from, $to
            )->map(fn ($r) => [ucfirst($report) => $r->label, 'Tickets' => $r->total]),
            default => abort(404),
        };

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            if ($rows->isNotEmpty()) {
                fputcsv($out, array_keys($rows->first()));
            }
            foreach ($rows as $row) {
                fputcsv($out, array_values($row));
            }
            fclose($out);
        }, "report-{$report}-{$from->toDateString()}-{$to->toDateString()}.csv", ['Content-Type' => 'text/csv']);
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function range(Request $request): array
    {
        $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from']]);

        return [
            Carbon::parse($request->query('from', today()->subDays(29)->toDateString()))->startOfDay(),
            Carbon::parse($request->query('to', today()->toDateString()))->endOfDay(),
        ];
    }

    private function summary(Carbon $from, Carbon $to): array
    {
        $row = Ticket::query()->whereBetween('created_at', [$from, $to])->selectRaw(<<<'SQL'
            COUNT(*) AS total,
            COUNT(resolved_at) AS resolved,
            COUNT(first_responded_at) AS responded,
            COUNT(*) FILTER (WHERE first_responded_at IS NOT NULL AND NOT response_breached) AS response_met,
            COUNT(*) FILTER (WHERE resolved_at IS NOT NULL AND NOT resolution_breached) AS resolution_met,
            AVG(EXTRACT(EPOCH FROM (first_responded_at - created_at)) / 60) AS avg_response,
            AVG(EXTRACT(EPOCH FROM (resolved_at - created_at)) / 60) AS avg_resolution,
            AVG(EXTRACT(EPOCH FROM (triaged_at - created_at)) / 60) FILTER (WHERE triaged_at > created_at) AS avg_triage
        SQL)->first();

        $pct = fn ($part, $whole) => $whole > 0 ? round($part / $whole * 100, 1) : null;

        return [
            'total' => (int) $row->total,
            'resolved' => (int) $row->resolved,
            'avg_response' => $row->avg_response !== null ? (int) round($row->avg_response) : null,
            'avg_resolution' => $row->avg_resolution !== null ? (int) round($row->avg_resolution) : null,
            'response_compliance' => $pct($row->response_met, $row->responded),
            'resolution_compliance' => $pct($row->resolution_met, $row->resolved),
            'avg_triage' => $row->avg_triage !== null ? (int) round($row->avg_triage) : null,
            'awaiting_triage' => Ticket::query()->needsTriage()->count(),
        ];
    }

    private function volumeBy(string $table, string $column, Carbon $from, Carbon $to): Collection
    {
        $hasColor = in_array($table, ['statuses', 'priorities'], true);

        return DB::table('tickets')
            ->leftJoin($table, "{$table}.id", '=', "tickets.{$column}")
            ->whereNull('tickets.deleted_at')
            ->whereBetween('tickets.created_at', [$from, $to])
            ->groupBy("{$table}.id", "{$table}.name", ...($hasColor ? ["{$table}.color"] : []))
            ->orderByDesc('total')
            ->get([
                DB::raw("COALESCE({$table}.name, '(none)') AS label"),
                DB::raw($hasColor ? "{$table}.color AS color" : 'NULL AS color'),
                DB::raw('COUNT(*) AS total'),
            ]);
    }

    private function agentPerformance(Carbon $from, Carbon $to): Collection
    {
        $minutes = fn ($col) => "AVG(EXTRACT(EPOCH FROM ({$col} - created_at)) / 60) FILTER (WHERE {$col} BETWEEN ? AND ?)";

        $stats = Ticket::query()
            ->whereNotNull('assignee_id')
            ->groupBy('assignee_id')
            ->selectRaw(
                'assignee_id,
                COUNT(*) FILTER (WHERE created_at BETWEEN ? AND ?) AS assigned,
                COUNT(*) FILTER (WHERE resolved_at BETWEEN ? AND ?) AS resolved,
                '.$minutes('first_responded_at').' AS avg_response,
                '.$minutes('resolved_at').' AS avg_resolution,
                COUNT(*) FILTER (WHERE created_at BETWEEN ? AND ? AND (response_breached OR resolution_breached)) AS breaches',
                [$from, $to, $from, $to, $from, $to, $from, $to, $from, $to]
            )
            ->get()
            ->keyBy('assignee_id');

        $openNow = Ticket::query()->open()->whereNotNull('assignee_id')
            ->groupBy('assignee_id')->selectRaw('assignee_id, COUNT(*) AS c')->pluck('c', 'assignee_id');

        return User::agents()->orderBy('name')->get()->map(function (User $agent) use ($stats, $openNow) {
            $s = $stats->get($agent->id);
            $agent->assigned = (int) ($s->assigned ?? 0);
            $agent->resolved = (int) ($s->resolved ?? 0);
            $agent->open_now = (int) ($openNow[$agent->id] ?? 0);
            $agent->avg_response = isset($s->avg_response) ? (int) round($s->avg_response) : null;
            $agent->avg_resolution = isset($s->avg_resolution) ? (int) round($s->avg_resolution) : null;
            $agent->breaches = (int) ($s->breaches ?? 0);

            return $agent;
        });
    }
}
