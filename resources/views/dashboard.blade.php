@extends('layouts.app')

@section('plugins.Chartjs', true)

@section('page_title', 'Dashboard')
@section('page_subtitle', 'Welcome back, '.auth()->user()->name)

@section('page_actions')
    <a href="{{ route('tickets.create') }}" class="btn btn-success"><i class="bi bi-plus-circle"></i> New ticket</a>
@stop

@section('body_content')
    @php $staff = auth()->user()->isStaff(); @endphp

    <div class="row">
        @if ($staff)
            @foreach ([
                ['Open tickets', $stats['open'], 'bi-inbox', 'primary', 'open'],
                ['Assigned to me', $stats['mine'], 'bi-person-workspace', 'info', 'mine'],
                ['Unassigned', $stats['unassigned'], 'bi-question-circle', 'warning', 'unassigned'],
                ['Overdue', $stats['overdue'], 'bi-alarm', 'danger', 'overdue'],
            ] as [$label, $value, $icon, $color, $view])
                <div class="col-lg-3 col-6">
                    <div class="small-box text-bg-{{ $color }}">
                        <div class="inner">
                            <h3>{{ $value }}</h3>
                            <p>{{ $label }}</p>
                        </div>
                        <i class="small-box-icon bi {{ $icon }}"></i>
                        <a href="{{ route('tickets.index', ['view' => $view]) }}" class="small-box-footer link-light link-underline-opacity-0 link-underline-opacity-50-hover">
                            View <i class="bi bi-arrow-right-circle"></i>
                        </a>
                    </div>
                </div>
            @endforeach
        @else
            @foreach ([
                ['My open requests', $stats['requested'], 'bi-send', 'primary', 'requested'],
                ['Resolved today', $stats['resolved_today'], 'bi-check2-circle', 'success', 'requested'],
            ] as [$label, $value, $icon, $color, $view])
                <div class="col-md-6">
                    <div class="small-box text-bg-{{ $color }}">
                        <div class="inner"><h3>{{ $value }}</h3><p>{{ $label }}</p></div>
                        <i class="small-box-icon bi {{ $icon }}"></i>
                        <a href="{{ route('tickets.index', ['view' => $view]) }}" class="small-box-footer link-light link-underline-opacity-0">View <i class="bi bi-arrow-right-circle"></i></a>
                    </div>
                </div>
            @endforeach
        @endif
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header"><h3 class="card-title"><i class="bi bi-graph-up"></i> Last 30 days</h3></div>
                <div class="card-body"><canvas id="trendChart" height="110"></canvas></div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header"><h3 class="card-title"><i class="bi bi-pie-chart"></i> By status</h3></div>
                <div class="card-body"><canvas id="statusChart" height="220"></canvas></div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="{{ $staff ? 'col-lg-7' : 'col-12' }}">
            <div class="card mb-4">
                <div class="card-header">
                    <h3 class="card-title"><i class="bi bi-lightning"></i> {{ $staff ? 'Needs attention' : 'My open requests' }}</h3>
                    <div class="card-tools small text-body-secondary">sorted by resolution due date</div>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover table-tickets mb-0">
                        <tbody>
                        @forelse ($attention as $t)
                            <tr>
                                <td style="width: 1%" class="text-nowrap"><a href="{{ route('tickets.show', $t) }}" class="fw-semibold">{{ $t->reference }}</a></td>
                                <td><a href="{{ route('tickets.show', $t) }}" class="link-body-emphasis link-underline-opacity-0">{{ \Illuminate\Support\Str::limit($t->subject, 60) }}</a></td>
                                <td class="text-nowrap"><x-priority-badge :priority="$t->priority" /></td>
                                <td class="text-nowrap"><x-sla-badge :ticket="$t" :state="$sla->state($t)" /></td>
                                <td class="text-nowrap">@if($t->assignee)<x-avatar :user="$t->assignee" size="xs" />@else<span class="badge text-bg-light border">Unassigned</span>@endif</td>
                            </tr>
                        @empty
                            <tr><td class="text-center text-body-secondary py-4"><i class="bi bi-emoji-smile"></i> Nothing needs your attention.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        @if ($staff)
            <div class="col-lg-5">
                <div class="card mb-4">
                    <div class="card-header"><h3 class="card-title"><i class="bi bi-activity"></i> Recent activity</h3></div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush">
                            @forelse ($recentActivity as $a)
                                <li class="list-group-item small">
                                    <strong>{{ $a->causer?->name ?? 'System' }}</strong>
                                    {{ $a->description }}
                                    @if ($a->subject)
                                        <a href="{{ route('tickets.show', $a->subject) }}">{{ $a->subject->reference }}</a>
                                    @endif
                                    <span class="float-end text-body-secondary">{{ $a->created_at->diffForHumans(short: true) }}</span>
                                </li>
                            @empty
                                <li class="list-group-item text-body-secondary small">No activity yet.</li>
                            @endforelse
                        </ul>
                    </div>
                </div>
            </div>
        @endif
    </div>
@stop

@push('js')
<script>
    window.addEventListener('load', () => {
        const css = getComputedStyle(document.documentElement);
        const color = (name) => css.getPropertyValue('--bs-' + name).trim() || '#6c757d';

        new Chart(document.getElementById('trendChart'), {
            type: 'line',
            data: {
                labels: @json($trend['labels']),
                datasets: [
                    { label: 'Created', data: @json($trend['created']), borderColor: color('primary'), backgroundColor: color('primary') + '33', fill: true, tension: .3 },
                    { label: 'Resolved', data: @json($trend['resolved']), borderColor: color('success'), backgroundColor: color('success') + '33', fill: true, tension: .3 },
                ],
            },
            options: { plugins: { legend: { position: 'bottom' } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } },
        });

        const statuses = @json($byStatus);
        new Chart(document.getElementById('statusChart'), {
            type: 'doughnut',
            data: {
                labels: statuses.map(s => s.label),
                datasets: [{ data: statuses.map(s => s.count), backgroundColor: statuses.map(s => color(s.color)) }],
            },
            options: { plugins: { legend: { position: 'bottom' } } },
        });
    });
</script>
@endpush
