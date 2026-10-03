@extends('layouts.app')

@section('page_title', 'Users')
@section('page_subtitle', $users->total().' users')

@section('page_actions')
    <a href="{{ route('admin.users.create') }}" class="btn btn-primary"><i class="bi bi-person-plus"></i> New user</a>
@stop

@section('body_content')
    <div class="card mb-3">
        <div class="card-body py-2">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-4"><input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Name or email"></div>
                <div class="col-md-3">
                    <select name="role" class="form-select form-select-sm">
                        <option value="">Any role</option>
                        @foreach (['admin', 'agent', 'requester'] as $r)<option value="{{ $r }}" @selected(request('role') === $r)>{{ ucfirst($r) }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="department" class="form-select form-select-sm">
                        <option value="">Any department</option>
                        @foreach ($departments as $d)<option value="{{ $d->id }}" @selected(request('department') == $d->id)>{{ $d->name }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-2"><button class="btn btn-sm btn-primary w-100"><i class="bi bi-funnel"></i> Filter</button></div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0 table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light"><tr><th>Name</th><th>Email</th><th>Role</th><th>Department</th><th class="text-end">Open assigned</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @foreach ($users as $u)
                    <tr class="{{ $u->is_active ? '' : 'opacity-50' }}">
                        <td><x-avatar :user="$u" size="xs" /> {{ $u->name }} <div class="small text-body-secondary">{{ $u->job_title }}</div></td>
                        <td>{{ $u->email }}</td>
                        <td>@foreach ($u->roles as $r)<span class="badge text-bg-{{ ['admin' => 'danger', 'agent' => 'primary'][$r->name] ?? 'secondary' }}">{{ ucfirst($r->name) }}</span>@endforeach @if ($u->permissions->contains('name', 'tickets.triage'))<span class="badge text-bg-warning">Triage</span>@endif</td>
                        <td>{{ $u->department?->name ?? '—' }}</td>
                        <td class="text-end">{{ $u->open_assigned_count }}</td>
                        <td>{!! $u->is_active ? '<span class="badge text-bg-success">Active</span>' : '<span class="badge text-bg-secondary">Inactive</span>' !!}</td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('admin.users.edit', $u) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                            @unless ($u->is(auth()->user()))
                                <form method="POST" action="{{ route('admin.users.destroy', $u) }}" class="d-inline">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm {{ $u->is_active ? 'btn-outline-danger' : 'btn-outline-success' }}" title="{{ $u->is_active ? 'Deactivate' : 'Reactivate' }}">
                                        <i class="bi {{ $u->is_active ? 'bi-person-x' : 'bi-person-check' }}"></i>
                                    </button>
                                </form>
                            @endunless
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @if ($users->hasPages())<div class="card-footer">{{ $users->links() }}</div>@endif
    </div>
@stop
