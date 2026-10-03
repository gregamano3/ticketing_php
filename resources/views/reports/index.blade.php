@extends('layouts.app')

@section('plugins.Chartjs', true)

@section('page_title', 'Reports')
@section('page_subtitle', 'Tickets created '.$from->toFormattedDateString().' – '.$to->toFormattedDateString())

@php
    $fmt = fn (?int $minutes) => $minutes === null ? '—' : \Carbon\CarbonInterval::minutes($minutes)->cascade()->forHumans(short: true, parts: 2);
    $range = ['from' => $from->toDateString(), 'to' => $to->toDateString()];
@endphp

@section('page_actions')
    <form method="GET" class="d-inline-flex gap-2 align-items-center">
        <input type="date" name="from" value="{{ $range['from'] }}" class="form-control form-control-sm">
        <span>–</span>
        <input type="date" name="to" value="{{ $range['to'] }}" class="form-control form-control-sm">
        <button class="btn btn-sm btn-primary">Apply</button>
    </form>
@stop

@section('body_content')
    <div class="row">
        @foreach ([
            ['Tickets created', $summary['total'], 'bi-inbox', 'primary'],
            ['Resolved', $summary['resolved'], 'bi-check2-circle', 'success'],
            ['Avg first response', $fmt($summary['avg_response']), 'bi-reply', 'info'],
            ['Avg resolution', $fmt($summary['avg_resolution']), 'bi-hourglass-bottom', 'secondary'],
        ] as [$label, $value, $icon, $color])
            <div class="col-md-6 col-xl-3">
                <div class="info-box mb-3">
                    <span class="info-box-icon text-bg-{{ $color }} shadow-sm"><i class="bi {{ $icon }}"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ $label }}</span>
                        <span class="info-box-number">{{ $value }}</span>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row">
        @foreach ([['First response SLA', $summary['response_compliance']], ['Resolution SLA', $summary['resolution_compliance']]] as [$label, $pct])
            @php $c = $pct === null ? 'secondary' : ($pct >= 90 ? 'success' : ($pct >= 75 ? 'warning' : 'danger')); @endphp
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-1"><strong>{{ $label }} compliance</strong><span>{{ $pct === null ? 'n/a' : $pct.'%' }}</span></div>
                        <div class="progress" role="progressbar" aria-valuenow="{{ $pct ?? 0 }}" aria-valuemin="0" aria-valuemax="100">
                            <div class="progress-bar bg-{{ $c }}" style="width: {{ $pct ?? 0 }}%"></div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row">
        @foreach ([
            'status' => ['By status', $byStatus, 'doughnut'],
            'priority' => ['By priority', $byPriority, 'doughnut'],
            'department' => ['By department', $byDepartment, 'bar'],
            'category' => ['By category', $byCategory, 'bar'],
        ] as $key => [$label, $rows, $type])
            <div class="col-lg-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <h3 class="card-title">{{ $label }}</h3>
                        <div class="card-tools"><a href="{{ route('reports.export', ['report' => $key, ...$range]) }}" class="btn btn-tool" title="Export CSV"><i class="bi bi-download"></i></a></div>
                    </div>
                    <div class="card-body">
                        @if ($rows->isEmpty())
                            <div class="text-center text-body-secondary py-4">No data for this period.</div>
                        @else
                            <canvas class="report-chart" height="200" data-type="{{ $type }}" data-rows='@json($rows)'></canvas>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h3 class="card-title"><i class="bi bi-people"></i> Agent performance</h3>
            <div class="card-tools"><a href="{{ route('reports.export', ['report' => 'agents', ...$range]) }}" class="btn btn-tool" title="Export CSV"><i class="bi bi-download"></i></a></div>
        </div>
        <div class="card-body p-0 table-responsive">
            <table class="table table-striped mb-0">
                <thead><tr><th>Agent</th><th class="text-end">Assigned</th><th class="text-end">Resolved</th><th class="text-end">Open now</th><th class="text-end">Avg first response</th><th class="text-end">Avg resolution</th><th class="text-end">SLA breaches</th></tr></thead>
                <tbody>
                @forelse ($agents as $a)
                    <tr>
                        <td><x-avatar :user="$a" size="xs" /> {{ $a->name }}</td>
                        <td class="text-end">{{ $a->assigned }}</td>
                        <td class="text-end">{{ $a->resolved }}</td>
                        <td class="text-end">{{ $a->open_now }}</td>
                        <td class="text-end">{{ $fmt($a->avg_response) }}</td>
                        <td class="text-end">{{ $fmt($a->avg_resolution) }}</td>
                        <td class="text-end">@if($a->breaches)<span class="badge text-bg-danger">{{ $a->breaches }}</span>@else 0 @endif</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-body-secondary">No agents.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@stop

@push('js')
<script>
    window._AdminLTE_Ready(() => {
        const css = getComputedStyle(document.documentElement);
        const palette = ['primary', 'success', 'warning', 'danger', 'info', 'secondary', 'dark'];
        const color = (name, i) => css.getPropertyValue('--bs-' + (name || palette[i % palette.length])).trim();

        document.querySelectorAll('canvas.report-chart').forEach(el => {
            const rows = JSON.parse(el.dataset.rows);
            const type = el.dataset.type;
            new Chart(el, {
                type,
                data: {
                    labels: rows.map(r => r.label),
                    datasets: [{ label: 'Tickets', data: rows.map(r => r.total), backgroundColor: rows.map((r, i) => color(r.color, i)) }],
                },
                options: {
                    indexAxis: type === 'bar' ? 'y' : undefined,
                    plugins: { legend: { display: type !== 'bar', position: 'bottom' } },
                    scales: type === 'bar' ? { x: { beginAtZero: true, ticks: { precision: 0 } } } : {},
                },
            });
        });
    });
</script>
@endpush
