{{-- Shared ticket properties. Expects $ticket (may be new) and the lookup collections. --}}
@php
    $staff = auth()->user()->isStaff();
    $selectedTags = old('tags', $ticket->exists ? $ticket->tags->pluck('id')->all() : []);
    $selectedWatchers = old('watchers', $ticket->exists ? $ticket->watchers->pluck('id')->all() : []);
@endphp

<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label" for="department_id">Department</label>
        <select name="department_id" id="department_id" class="form-select">
            <option value="">— Not sure —</option>
            @foreach ($departments as $d)
                <option value="{{ $d->id }}" @selected(old('department_id', $ticket->department_id) == $d->id)>{{ $d->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="category_id">Category</label>
        <select name="category_id" id="category_id" class="form-select">
            <option value="">— None —</option>
            @foreach ($categories as $c)
                <option value="{{ $c->id }}" data-department="{{ $c->department_id }}" @selected(old('category_id', $ticket->category_id) == $c->id)>{{ $c->full_name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="priority_id">Priority</label>
        <select name="priority_id" id="priority_id" class="form-select">
            @foreach ($priorities as $p)
                <option value="{{ $p->id }}" @selected(old('priority_id', $ticket->priority_id ?? $priorities->firstWhere('is_default', true)?->id) == $p->id)>
                    {{ $p->name }} — response {{ \Carbon\CarbonInterval::minutes($p->response_minutes)->cascade()->forHumans(short: true) }}, resolution {{ \Carbon\CarbonInterval::minutes($p->resolution_minutes)->cascade()->forHumans(short: true) }}
                </option>
            @endforeach
        </select>
    </div>

    @if ($staff)
        @if ($ticket->exists)
            <div class="col-md-6">
                <label class="form-label" for="status_id">Status</label>
                <select name="status_id" id="status_id" class="form-select">
                    @foreach ($statuses as $s)
                        <option value="{{ $s->id }}" @selected(old('status_id', $ticket->status_id) == $s->id)>{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>
        @else
            <div class="col-md-6">
                <label class="form-label" for="requester_id">Requester</label>
                <select name="requester_id" id="requester_id" class="form-select tom-select">
                    @foreach ($users as $u)
                        <option value="{{ $u->id }}" @selected(old('requester_id', auth()->id()) == $u->id)>{{ $u->name }} ({{ $u->email }})</option>
                    @endforeach
                </select>
                <div class="form-text">Open the ticket on behalf of a colleague.</div>
            </div>
        @endif
        <div class="col-md-6">
            <label class="form-label" for="assignee_id">Assignee</label>
            <select name="assignee_id" id="assignee_id" class="form-select tom-select">
                <option value="">{{ $ticket->exists ? '— Unassigned —' : '— Auto-assign —' }}</option>
                @foreach ($agents as $a)
                    <option value="{{ $a->id }}" @selected(old('assignee_id', $ticket->assignee_id) == $a->id)>{{ $a->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="tags">Tags</label>
            <input type="hidden" name="tags" value="">
            <select name="tags[]" id="tags" class="form-select tom-select" multiple>
                @foreach ($tags as $tg)
                    <option value="{{ $tg->id }}" @selected(in_array($tg->id, $selectedTags))>{{ $tg->name }}</option>
                @endforeach
            </select>
        </div>
    @endif

    <div class="col-md-6">
        <label class="form-label" for="watchers">CC / watchers</label>
        <input type="hidden" name="watchers" value="">
        <select name="watchers[]" id="watchers" class="form-select tom-select" multiple>
            @foreach ($users as $u)
                <option value="{{ $u->id }}" @selected(in_array($u->id, $selectedWatchers))>{{ $u->name }}</option>
            @endforeach
        </select>
        <div class="form-text">Watchers get email updates on this ticket.</div>
    </div>
</div>

@push('js')
<script>
    window._AdminLTE_Ready(() => {
        document.querySelectorAll('select.tom-select').forEach(el => new TomSelect(el, {
            plugins: el.multiple ? ['remove_button'] : [],
            allowEmptyOption: true,
            maxOptions: 500,
        }));

        // Narrow categories to the chosen department, and pick the department from the category.
        const dept = document.getElementById('department_id');
        const cat = document.getElementById('category_id');
        const filterCategories = () => {
            [...cat.options].forEach(o => {
                if (!o.value) return;
                o.hidden = dept.value && o.dataset.department && o.dataset.department !== dept.value;
            });
            if (cat.selectedOptions[0]?.hidden) cat.value = '';
        };
        dept.addEventListener('change', filterCategories);
        cat.addEventListener('change', () => {
            const d = cat.selectedOptions[0]?.dataset.department;
            if (d && !dept.value) { dept.value = d; filterCategories(); }
        });
        filterCategories();
    });
</script>
@endpush
