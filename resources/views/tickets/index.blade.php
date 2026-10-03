@extends('layouts.app')

@section('page_title', $views[$view] ?? 'Tickets')
@section('page_subtitle', $tickets->total().' '.\Illuminate\Support\Str::plural('ticket', $tickets->total()))

@section('page_actions')
    @if (auth()->user()->isStaff())
        <a href="{{ route('tickets.export', request()->query()) }}" class="btn btn-outline-secondary"><i class="bi bi-download"></i> Export CSV</a>
    @endif
    <a href="{{ route('tickets.create') }}" class="btn btn-success"><i class="bi bi-plus-circle"></i> New ticket</a>
@stop

@php
    $sortLink = function (string $column, string $label) {
        $current = request('sort', 'updated_at');
        $dir = $current === $column && request('dir') !== 'asc' ? 'asc' : 'desc';
        $icon = $current === $column ? (request('dir') === 'asc' ? 'bi-sort-up' : 'bi-sort-down') : '';
        $url = request()->fullUrlWithQuery(['sort' => $column, 'dir' => $dir, 'page' => null]);

        return '<a href="'.e($url).'" class="link-body-emphasis link-underline-opacity-0">'.e($label).' <i class="bi '.$icon.'"></i></a>';
    };
    $staff = auth()->user()->isStaff();
@endphp

@section('body_content')
    <ul class="nav nav-pills mb-3 flex-wrap">
        @foreach ($views as $key => $label)
            <li class="nav-item">
                <a class="nav-link {{ $view === $key ? 'active' : '' }}" href="{{ route('tickets.index', ['view' => $key]) }}">{{ $label }}</a>
            </li>
        @endforeach
    </ul>

    <div class="card mb-3">
        <div class="card-body py-2">
            <form method="GET" class="row g-2 align-items-end filter-bar">
                <input type="hidden" name="view" value="{{ $view }}">
                <div class="col-md-3">
                    <label class="form-label small mb-0">Search</label>
                    <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Reference, subject, text…">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-0">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">Any</option>
                        @foreach ($statuses as $s)<option value="{{ $s->id }}" @selected(request('status') == $s->id)>{{ $s->name }}</option>@endforeach
                    </select>
                </div>
                <div class="col-6 col-md-1">
                    <label class="form-label small mb-0">Priority</label>
                    <select name="priority" class="form-select form-select-sm">
                        <option value="">Any</option>
                        @foreach ($priorities as $p)<option value="{{ $p->id }}" @selected(request('priority') == $p->id)>{{ $p->name }}</option>@endforeach
                    </select>
                </div>
                @if ($staff)
                    <div class="col-6 col-md-2">
                        <label class="form-label small mb-0">Department</label>
                        <select name="department" class="form-select form-select-sm">
                            <option value="">Any</option>
                            @foreach ($departments as $d)<option value="{{ $d->id }}" @selected(request('department') == $d->id)>{{ $d->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small mb-0">Assignee</label>
                        <select name="assignee" class="form-select form-select-sm">
                            <option value="">Anyone</option>
                            <option value="none" @selected(request('assignee') === 'none')>Unassigned</option>
                            @foreach ($agents as $a)<option value="{{ $a->id }}" @selected(request('assignee') == $a->id)>{{ $a->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small mb-0">Tag</label>
                        <select name="tag" class="form-select form-select-sm">
                            <option value="">Any</option>
                            @foreach ($tags as $tg)<option value="{{ $tg->id }}" @selected(request('tag') == $tg->id)>{{ $tg->name }}</option>@endforeach
                        </select>
                    </div>
                @endif
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-0">Created from</label>
                    <input type="date" name="from" value="{{ request('from') }}" class="form-control form-control-sm">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-0">Created to</label>
                    <input type="date" name="to" value="{{ request('to') }}" class="form-control form-control-sm">
                </div>
                @if ($staff)
                    <div class="col-6 col-md-2">
                        <div class="form-check mt-3">
                            <input class="form-check-input" type="checkbox" name="breached" value="1" id="f-breached" @checked(request()->boolean('breached'))>
                            <label class="form-check-label small" for="f-breached">SLA breached only</label>
                        </div>
                    </div>
                @endif
                <div class="col-md-auto ms-auto">
                    <button class="btn btn-sm btn-primary"><i class="bi bi-funnel"></i> Filter</button>
                    <a href="{{ route('tickets.index', ['view' => $view]) }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0 table-responsive">
            <table class="table table-hover table-tickets mb-0">
                <thead class="table-light">
                    <tr>
                        <th>{!! $sortLink('reference', 'Ref') !!}</th>
                        <th>Subject</th>
                        <th>Status</th>
                        <th>{!! $sortLink('priority', 'Priority') !!}</th>
                        @if ($staff)<th>Requester</th>@endif
                        <th>Assignee</th>
                        <th>{!! $sortLink('due_resolution_at', 'SLA') !!}</th>
                        <th class="text-end">{!! $sortLink('updated_at', 'Updated') !!}</th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($tickets as $t)
                    <tr>
                        <td class="text-nowrap"><a href="{{ route('tickets.show', $t) }}" class="fw-semibold">{{ $t->reference }}</a></td>
                        <td>
                            <a href="{{ route('tickets.show', $t) }}" class="link-body-emphasis link-underline-opacity-0 ticket-subject">{{ \Illuminate\Support\Str::limit($t->subject, 70) }}</a>
                            <div class="small text-body-secondary">
                                {{ $t->department?->name ?? 'No department' }}
                                · <i class="bi bi-chat"></i> {{ $t->replies_count }}
                                <x-tag-badges :tags="$t->tags" />
                            </div>
                        </td>
                        <td><x-status-badge :status="$t->status" /></td>
                        <td><x-priority-badge :priority="$t->priority" /></td>
                        @if ($staff)
                            <td class="text-nowrap"><x-avatar :user="$t->requester" size="xs" /> <span class="small">{{ $t->requester?->name }}</span></td>
                        @endif
                        <td class="text-nowrap">
                            @if ($t->assignee)
                                <x-avatar :user="$t->assignee" size="xs" /> <span class="small">{{ $t->assignee->name }}</span>
                            @else
                                <span class="badge text-bg-light border">Unassigned</span>
                            @endif
                        </td>
                        <td class="text-nowrap"><x-sla-badge :ticket="$t" :state="$sla->state($t)" /></td>
                        <td class="text-end text-nowrap small text-body-secondary" title="{{ $t->updated_at }}">{{ $t->updated_at->diffForHumans() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-body-secondary py-5"><i class="bi bi-inbox fs-2 d-block"></i>No tickets match.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($tickets->hasPages())
            <div class="card-footer">{{ $tickets->links() }}</div>
        @endif
    </div>
@stop
