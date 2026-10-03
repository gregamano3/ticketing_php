@extends('layouts.app')

@section('plugins.TomSelect', true)
@section('title', $ticket->reference.' · '.$ticket->subject)

@section('page_title')
    <span class="text-body-secondary">{{ $ticket->reference }}</span> {{ $ticket->subject }}
@stop

@section('page_subtitle')
    Opened by {{ $ticket->requester?->name }} {{ $ticket->created_at->diffForHumans() }} · via {{ $ticket->source }}
@stop

@php
    $user = auth()->user();
    $staff = $user->isStaff();
    $canUpdate = $user->can('update', $ticket);
    $watching = $ticket->watchers->contains($user);
    $presenter = app(\App\Support\ActivityPresenter::class);
@endphp

@section('page_actions')
    <form method="POST" action="{{ route('tickets.watch', $ticket) }}" class="d-inline">
        @csrf
        <button class="btn btn-outline-secondary btn-sm"><i class="bi {{ $watching ? 'bi-eye-slash' : 'bi-eye' }}"></i> {{ $watching ? 'Unwatch' : 'Watch' }}</button>
    </form>
    @if ($canUpdate)
        @if ($ticket->assignee_id !== $user->id)
            <form method="POST" action="{{ route('tickets.claim', $ticket) }}" class="d-inline">
                @csrf
                <button class="btn btn-outline-primary btn-sm"><i class="bi bi-person-check"></i> Assign to me</button>
            </form>
        @endif
        <a href="{{ route('tickets.edit', $ticket) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil"></i> Edit</a>
    @endif
    @can('sendBackToTriage', $ticket)
        <button type="button" class="btn btn-outline-warning btn-sm" data-bs-toggle="modal" data-bs-target="#send-back-modal"><i class="bi bi-arrow-return-left"></i> Send back to triage</button>
    @endcan
    @can('delete', $ticket)
        <form method="POST" action="{{ route('tickets.destroy', $ticket) }}" class="d-inline" onsubmit="return confirm('Delete ticket {{ $ticket->reference }}?')">
            @csrf @method('DELETE')
            <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
        </form>
    @endcan
@stop

@section('body_content')
<div class="row">
    {{-- Conversation --}}
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header d-flex align-items-center gap-2">
                <x-avatar :user="$ticket->requester" />
                <div>
                    <strong>{{ $ticket->requester?->name }}</strong>
                    <div class="small text-body-secondary">{{ $ticket->requester?->job_title }} {{ $ticket->requester?->department ? '· '.$ticket->requester->department->name : '' }}</div>
                </div>
                <div class="ms-auto d-flex gap-1 flex-wrap">
                    @if ($ticket->needsTriage())<span class="badge text-bg-warning"><i class="bi bi-signpost-split"></i> Needs triage</span>@endif
                    <x-status-badge :status="$ticket->status" />
                    <x-priority-badge :priority="$ticket->priority" />
                    @if ($staff)<x-sla-badge :ticket="$ticket" :state="$slaState" />@endif
                </div>
            </div>
            <div class="card-body">
                <div class="reply-body">{{ $ticket->description }}</div>
                @if ($ticket->impact && $ticket->urgency)
                    <div class="small text-body-secondary mt-2">
                        <i class="bi bi-people"></i> {{ \App\Support\PriorityMatrix::IMPACT[$ticket->impact] ?? '' }}
                        · <i class="bi bi-lightning"></i> {{ \App\Support\PriorityMatrix::URGENCY[$ticket->urgency] ?? '' }}
                    </div>
                @endif
                @include('tickets._attachments', ['attachments' => $ticket->attachments])
                @if ($ticket->tags->isNotEmpty())
                    <div class="mt-3"><x-tag-badges :tags="$ticket->tags" /></div>
                @endif
            </div>
        </div>

        @if ($ticket->replies->isNotEmpty())
            <div class="timeline mb-4">
                @foreach ($ticket->replies->groupBy(fn ($r) => $r->created_at->toDateString()) as $day => $replies)
                    <div class="time-label"><span class="text-bg-secondary">{{ \Carbon\Carbon::parse($day)->toFormattedDateString() }}</span></div>
                    @foreach ($replies as $reply)
                        @php $fromStaff = $reply->user?->isStaff() && $reply->user_id !== $ticket->requester_id; @endphp
                        <div id="reply-{{ $reply->id }}" class="{{ $reply->is_internal ? 'timeline-internal' : '' }}">
                            <i class="timeline-icon bi {{ $reply->is_internal ? 'bi-lock-fill text-bg-warning' : ($fromStaff ? 'bi-headset text-bg-primary' : 'bi-person-fill text-bg-success') }}"></i>
                            <div class="timeline-item">
                                <span class="time" title="{{ $reply->created_at }}"><i class="bi bi-clock"></i> {{ $reply->created_at->format('H:i') }}</span>
                                <h3 class="timeline-header">
                                    <x-avatar :user="$reply->user" size="xs" />
                                    <strong>{{ $reply->user?->name }}</strong>
                                    @if ($reply->is_internal)
                                        <span class="badge text-bg-warning ms-1"><i class="bi bi-lock"></i> Internal note</span>
                                    @elseif ($fromStaff)
                                        <span class="badge text-bg-primary-subtle text-primary-emphasis ms-1">Support</span>
                                    @endif
                                </h3>
                                <div class="timeline-body">
                                    <div class="reply-body">{{ $reply->body }}</div>
                                    @include('tickets._attachments', ['attachments' => $reply->attachments])
                                </div>
                            </div>
                        </div>
                    @endforeach
                @endforeach
                <div><i class="timeline-icon bi bi-clock-fill text-bg-secondary"></i></div>
            </div>
        @endif

        {{-- Reply box --}}
        @can('reply', $ticket)
            <div class="card mb-4" id="reply">
                <form method="POST" action="{{ route('tickets.replies.store', $ticket) }}" enctype="multipart/form-data">
                    @csrf
                    <div class="card-header">
                        <h3 class="card-title"><i class="bi bi-reply"></i> {{ $ticket->isOpen() ? 'Reply' : 'Reply (this will reopen the ticket)' }}</h3>
                        @if ($staff && $canned->isNotEmpty())
                            <div class="card-tools">
                                <select id="canned-picker" class="form-select form-select-sm" style="min-width: 220px">
                                    <option value="">Insert canned reply…</option>
                                    @foreach ($canned as $c)
                                        <option value="{{ $c->id }}">{{ $c->title }}{{ $c->isShared() ? '' : ' (personal)' }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                    </div>
                    <div class="card-body">
                        <textarea name="body" id="reply-body" rows="6" class="form-control @error('body') is-invalid @enderror" required placeholder="Write your reply…">{{ old('body') }}</textarea>
                        @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="row g-2 mt-2 align-items-center">
                            <div class="col-md-6">
                                <input type="file" name="attachments[]" class="form-control form-control-sm" multiple>
                            </div>
                            @if ($staff)
                                <div class="col-md-3">
                                    <select name="status_id" class="form-select form-select-sm" title="Change status on send">
                                        <option value="">Keep status ({{ $ticket->status?->name }})</option>
                                        @foreach ($statuses as $s)
                                            @continue($s->id === $ticket->status_id)
                                            <option value="{{ $s->id }}">Set to {{ $s->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" role="switch" name="is_internal" value="1" id="is_internal">
                                        <label class="form-check-label small" for="is_internal"><i class="bi bi-lock"></i> Internal note</label>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                    <div class="card-footer text-end">
                        <button type="submit" class="btn btn-primary" id="reply-submit"><i class="bi bi-send"></i> Send reply</button>
                    </div>
                </form>
            </div>
        @endcan
    </div>

    {{-- Sidebar --}}
    <div class="col-lg-4">
        @can('triage', $ticket)
            <div class="card card-outline card-warning mb-4" id="triage-card">
                <div class="card-header"><h3 class="card-title"><i class="bi bi-signpost-split"></i> Triage</h3></div>
                <form method="POST" action="{{ route('tickets.triage', $ticket) }}">
                    @csrf
                    <div class="card-body">
                        <p class="small text-body-secondary mb-2">Confirm where this ticket goes and how urgent it is. Leave the assignee empty to auto-assign within the department.</p>
                        <div class="mb-2">
                            <label class="form-label small mb-0" for="triage_department">Department</label>
                            <select name="department_id" id="triage_department" class="form-select form-select-sm" required>
                                <option value="">— Choose —</option>
                                @foreach ($departments as $d)<option value="{{ $d->id }}" @selected($ticket->department_id === $d->id)>{{ $d->name }}</option>@endforeach
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small mb-0" for="triage_category">Category</label>
                            <select name="category_id" id="triage_category" class="form-select form-select-sm">
                                <option value="">—</option>
                                @foreach ($categories as $c)<option value="{{ $c->id }}" data-department="{{ $c->department_id }}" @selected($ticket->category_id === $c->id)>{{ $c->full_name }}</option>@endforeach
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small mb-0" for="triage_priority">Priority</label>
                            <select name="priority_id" id="triage_priority" class="form-select form-select-sm" required>
                                @foreach ($priorities as $p)<option value="{{ $p->id }}" @selected($ticket->priority_id === $p->id)>{{ $p->name }}</option>@endforeach
                            </select>
                            @if ($ticket->impact && $ticket->urgency)
                                <div class="form-text">Suggested from impact/urgency: {{ \App\Support\PriorityMatrix::suggest($ticket->impact, $ticket->urgency, $priorities)?->name }}</div>
                            @endif
                        </div>
                        <div>
                            <label class="form-label small mb-0" for="triage_assignee">Assignee</label>
                            <select name="assignee_id" id="triage_assignee" class="form-select form-select-sm">
                                <option value="">— Auto-assign —</option>
                                @foreach ($agents as $a)<option value="{{ $a->id }}" data-department="{{ $a->department_id }}" @selected($ticket->assignee_id === $a->id)>{{ $a->name }}{{ $a->department ? ' ('.$a->department->name.')' : '' }}</option>@endforeach
                            </select>
                        </div>
                    </div>
                    <div class="card-footer text-end">
                        <button class="btn btn-warning btn-sm"><i class="bi bi-check2-circle"></i> Complete triage</button>
                    </div>
                </form>
            </div>
        @endcan

        @if ($canUpdate)
            <div class="card card-outline card-primary mb-4">
                <div class="card-header"><h3 class="card-title"><i class="bi bi-sliders"></i> Properties</h3></div>
                <form method="POST" action="{{ route('tickets.update', $ticket) }}">
                    @csrf @method('PATCH')
                    <div class="card-body">
                        <div class="mb-2">
                            <label class="form-label small mb-0">Status</label>
                            <select name="status_id" class="form-select form-select-sm">
                                @foreach ($statuses as $s)<option value="{{ $s->id }}" @selected($ticket->status_id === $s->id)>{{ $s->name }}</option>@endforeach
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small mb-0">Priority</label>
                            <select name="priority_id" class="form-select form-select-sm">
                                @foreach ($priorities as $p)<option value="{{ $p->id }}" @selected($ticket->priority_id === $p->id)>{{ $p->name }}</option>@endforeach
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small mb-0">Assignee</label>
                            <select name="assignee_id" class="form-select form-select-sm tom-select">
                                <option value="">— Unassigned —</option>
                                @foreach ($agents as $a)<option value="{{ $a->id }}" @selected($ticket->assignee_id === $a->id)>{{ $a->name }}</option>@endforeach
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small mb-0">Department</label>
                            <select name="department_id" class="form-select form-select-sm">
                                <option value="">—</option>
                                @foreach ($departments as $d)<option value="{{ $d->id }}" @selected($ticket->department_id === $d->id)>{{ $d->name }}</option>@endforeach
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small mb-0">Category</label>
                            <select name="category_id" class="form-select form-select-sm">
                                <option value="">—</option>
                                @foreach ($categories as $c)<option value="{{ $c->id }}" @selected($ticket->category_id === $c->id)>{{ $c->full_name }}</option>@endforeach
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small mb-0">Tags</label>
                            <input type="hidden" name="tags" value="">
                            <select name="tags[]" class="form-select form-select-sm tom-select" multiple>
                                @foreach ($tags as $tg)<option value="{{ $tg->id }}" @selected($ticket->tags->contains($tg))>{{ $tg->name }}</option>@endforeach
                            </select>
                        </div>
                        <div>
                            <label class="form-label small mb-0">Watchers</label>
                            <input type="hidden" name="watchers" value="">
                            <select name="watchers[]" class="form-select form-select-sm tom-select" multiple>
                                @foreach ($users as $u)<option value="{{ $u->id }}" @selected($ticket->watchers->contains($u))>{{ $u->name }}</option>@endforeach
                            </select>
                        </div>
                    </div>
                    <div class="card-footer text-end">
                        <button class="btn btn-primary btn-sm"><i class="bi bi-save"></i> Update</button>
                    </div>
                </form>
            </div>
        @else
            <div class="card mb-4">
                <div class="card-header"><h3 class="card-title"><i class="bi bi-info-circle"></i> Details</h3></div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <tr><th class="ps-3">Status</th><td><x-status-badge :status="$ticket->status" /></td></tr>
                        <tr><th class="ps-3">Priority</th><td><x-priority-badge :priority="$ticket->priority" /></td></tr>
                        <tr><th class="ps-3">Department</th><td>{{ $ticket->department?->name ?? '—' }}</td></tr>
                        <tr><th class="ps-3">Category</th><td>{{ $ticket->category?->full_name ?? '—' }}</td></tr>
                        <tr><th class="ps-3">Assigned to</th><td>{{ $ticket->assignee?->name ?? 'Not yet assigned' }}</td></tr>
                        <tr><th class="ps-3">Watchers</th><td>{{ $ticket->watchers->pluck('name')->join(', ') ?: '—' }}</td></tr>
                    </table>
                </div>
            </div>
        @endif

        <div class="card mb-4">
            <div class="card-header"><h3 class="card-title"><i class="bi bi-stopwatch"></i> SLA</h3></div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0 small">
                    <tr>
                        <th class="ps-3">First response due</th>
                        <td class="{{ $ticket->response_breached ? 'text-danger fw-semibold' : '' }}">{{ $ticket->due_response_at?->toDayDateTimeString() ?? '—' }}</td>
                    </tr>
                    <tr>
                        <th class="ps-3">First response</th>
                        <td>{{ $ticket->first_responded_at?->toDayDateTimeString() ?? 'Awaiting' }}</td>
                    </tr>
                    <tr>
                        <th class="ps-3">Resolution due</th>
                        <td class="{{ $ticket->resolution_breached ? 'text-danger fw-semibold' : '' }}">{{ $ticket->due_resolution_at?->toDayDateTimeString() ?? '—' }}</td>
                    </tr>
                    @if ($ticket->resolved_at)
                        <tr><th class="ps-3">Resolved</th><td>{{ $ticket->resolved_at->toDayDateTimeString() }}</td></tr>
                    @endif
                    @if ($ticket->sla_paused_at)
                        <tr><th class="ps-3">Paused since</th><td>{{ $ticket->sla_paused_at->toDayDateTimeString() }}</td></tr>
                    @endif
                    @if ($staff && $ticket->escalation_level > 0)
                        <tr><th class="ps-3">Escalation</th><td><span class="badge text-bg-danger">Level {{ $ticket->escalation_level }}</span></td></tr>
                    @endif
                </table>
            </div>
            @if ($staff && $ticket->escalations->isNotEmpty())
                <div class="card-footer small">
                    @foreach ($ticket->escalations->sortByDesc('triggered_at') as $e)
                        <div><i class="bi bi-exclamation-triangle text-danger"></i> L{{ $e->level }} {{ $e->type }} → {{ $e->escalatedTo?->name ?? 'admins' }} <span class="text-body-secondary">{{ $e->triggered_at->diffForHumans() }}</span></div>
                    @endforeach
                </div>
            @endif
        </div>

        @if ($staff)
            <div class="card mb-4">
                <div class="card-header">
                    <h3 class="card-title"><i class="bi bi-clock-history"></i> Audit log</h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-tool" data-lte-toggle="card-collapse"><i data-lte-icon="expand" class="bi bi-plus-lg"></i><i data-lte-icon="collapse" class="bi bi-dash-lg"></i></button>
                    </div>
                </div>
                <div class="card-body p-0" style="max-height: 420px; overflow-y: auto">
                    <ul class="list-group list-group-flush small">
                        @forelse ($activity as $a)
                            <li class="list-group-item">
                                <i class="{{ $presenter->icon($a) }}"></i>
                                <strong>{{ $a->causer?->name ?? 'System' }}</strong> {{ $a->description }}
                                <span class="float-end text-body-secondary" title="{{ $a->created_at }}">{{ $a->created_at->diffForHumans(short: true) }}</span>
                                @foreach ($presenter->changes($a) as $line)
                                    <div class="text-body-secondary ms-3">{{ $line }}</div>
                                @endforeach
                            </li>
                        @empty
                            <li class="list-group-item text-body-secondary">No activity recorded.</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        @endif
    </div>
</div>
@can('sendBackToTriage', $ticket)
    <div class="modal fade" id="send-back-modal" tabindex="-1" aria-labelledby="send-back-title" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" action="{{ route('tickets.send-back', $ticket) }}" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="send-back-title">Send back to triage</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-body-secondary">The ticket will be unassigned and returned to the triage queue. The reason is saved as an internal note.</p>
                    <label class="form-label" for="send-back-reason">Reason</label>
                    <textarea name="reason" id="send-back-reason" rows="3" class="form-control" required maxlength="1000" placeholder="e.g. This is a Facilities issue, not IT."></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-warning"><i class="bi bi-arrow-return-left"></i> Send back</button>
                </div>
            </form>
        </div>
    </div>
@endcan
@stop

@push('js')
<script>
    window._AdminLTE_Ready(() => {
        document.querySelectorAll('select.tom-select').forEach(el => new TomSelect(el, {
            plugins: el.multiple ? ['remove_button'] : [], allowEmptyOption: true, maxOptions: 500,
        }));

        const canned = @json($canned->mapWithKeys(fn ($c) => [$c->id => $c->body]));
        const requester = @json($ticket->requester?->name);
        const agent = @json(auth()->user()->name);
        const picker = document.getElementById('canned-picker');
        const body = document.getElementById('reply-body');
        picker?.addEventListener('change', () => {
            if (!picker.value) return;
            const text = canned[picker.value]
                .replaceAll('{requester}', requester ?? '')
                .replaceAll('{agent}', agent)
                .replaceAll('{reference}', @json($ticket->reference));
            body.value = body.value ? body.value.trimEnd() + '\n\n' + text : text;
            body.focus();
            picker.value = '';
        });

        // Triage card: narrow categories and assignees to the chosen department.
        const tDept = document.getElementById('triage_department');
        if (tDept) {
            const narrow = (select) => {
                [...select.options].forEach(o => {
                    if (!o.value) return;
                    o.hidden = tDept.value && o.dataset.department && o.dataset.department !== tDept.value;
                });
                if (select.selectedOptions[0]?.hidden) select.value = '';
            };
            const tCat = document.getElementById('triage_category');
            const tAssignee = document.getElementById('triage_assignee');
            tDept.addEventListener('change', () => { narrow(tCat); narrow(tAssignee); });
            tCat.addEventListener('change', () => {
                const d = tCat.selectedOptions[0]?.dataset.department;
                if (d && !tDept.value) { tDept.value = d; narrow(tCat); narrow(tAssignee); }
            });
            narrow(tCat); narrow(tAssignee);
        }

        // Make the internal-note state obvious before sending.
        const internal = document.getElementById('is_internal');
        const submit = document.getElementById('reply-submit');
        const syncSubmit = () => {
            if (!internal) return;
            submit.classList.toggle('btn-primary', !internal.checked);
            submit.classList.toggle('btn-warning', internal.checked);
            submit.innerHTML = internal.checked ? '<i class="bi bi-lock"></i> Add internal note' : '<i class="bi bi-send"></i> Send reply';
        };
        internal?.addEventListener('change', syncSubmit);
        syncSubmit(); // the browser may restore the checkbox state on back/forward
    });
</script>
@endpush
