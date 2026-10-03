@extends('layouts.app')

@section('page_title', $user->exists ? 'Edit '.$user->name : 'New user')

@section('body_content')
    @php $isSelf = $user->exists && $user->is(auth()->user()); @endphp
    <form method="POST" action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}">
        @csrf
        @if ($user->exists) @method('PUT') @endif
        <div class="card" style="max-width: 900px">
            <div class="card-body row g-3">
                <div class="col-md-6"><label class="form-label">Name</label><input name="name" value="{{ old('name', $user->name) }}" class="form-control" required></div>
                <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" value="{{ old('email', $user->email) }}" class="form-control" required></div>
                <div class="col-md-4">
                    <label class="form-label">Role</label>
                    <select name="role" class="form-select" @disabled($isSelf)>
                        @foreach (['requester' => 'Requester — raises tickets', 'agent' => 'Agent — works tickets', 'admin' => 'Admin — full access'] as $r => $label)
                            <option value="{{ $r }}" @selected(old('role', $user->getRoleNames()->first() ?? 'requester') === $r)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @if ($isSelf)<input type="hidden" name="role" value="{{ $user->getRoleNames()->first() }}"><div class="form-text">You can't change your own role.</div>@endif
                </div>
                <div class="col-md-4">
                    <label class="form-label">Department</label>
                    <select name="department_id" class="form-select">
                        <option value="">—</option>
                        @foreach ($departments as $d)<option value="{{ $d->id }}" @selected(old('department_id', $user->department_id) == $d->id)>{{ $d->name }}</option>@endforeach
                    </select>
                    <div class="form-text">Agents see and auto-receive tickets of their department.</div>
                </div>
                <div class="col-md-4"><label class="form-label">Job title</label><input name="job_title" value="{{ old('job_title', $user->job_title) }}" class="form-control"></div>
                <div class="col-md-4"><label class="form-label">Phone</label><input name="phone" value="{{ old('phone', $user->phone) }}" class="form-control"></div>
                <div class="col-md-4"><label class="form-label">Password</label><input type="password" name="password" class="form-control" autocomplete="new-password" @required(! $user->exists)>@if($user->exists)<div class="form-text">Leave empty to keep the current password.</div>@endif</div>
                <div class="col-md-4"><label class="form-label">Confirm password</label><input type="password" name="password_confirmation" class="form-control" autocomplete="new-password"></div>
                <div class="col-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" id="is_active" @checked(old('is_active', $user->is_active)) @disabled($isSelf)>
                        <label class="form-check-label" for="is_active">Active (can sign in)</label>
                    </div>
                </div>
            </div>
            <div class="card-footer text-end">
                <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">Cancel</a>
                <button class="btn btn-primary"><i class="bi bi-save"></i> Save</button>
            </div>
        </div>
    </form>
@stop
