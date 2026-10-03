@extends('layouts.app')

@section('page_title', 'My profile')

@section('body_content')
    <div class="row">
        <div class="col-lg-4">
            <div class="card card-outline card-primary mb-4">
                <div class="card-body text-center">
                    <img src="{{ $user->adminlte_image() }}" class="rounded-circle mb-2" width="96" height="96" alt="{{ $user->name }}">
                    <h4 class="mb-0">{{ $user->name }}</h4>
                    <div class="text-body-secondary">{{ $user->job_title }}</div>
                    <div class="mt-1">
                        @foreach ($user->getRoleNames() as $role)<span class="badge text-bg-primary">{{ ucfirst($role) }}</span>@endforeach
                        @if ($user->department)<span class="badge text-bg-secondary">{{ $user->department->name }}</span>@endif
                    </div>
                </div>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between">Tickets requested <strong>{{ $stats['requested'] }}</strong></li>
                    @if ($user->isStaff())
                        <li class="list-group-item d-flex justify-content-between">Open assigned <strong>{{ $stats['assigned_open'] }}</strong></li>
                        <li class="list-group-item d-flex justify-content-between">Resolved <strong>{{ $stats['resolved'] }}</strong></li>
                    @endif
                </ul>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header"><h3 class="card-title">Details</h3></div>
                <form method="POST" action="{{ route('profile.update') }}">
                    @csrf @method('PUT')
                    <div class="card-body row g-3">
                        <div class="col-md-6"><label class="form-label">Name</label><input name="name" value="{{ old('name', $user->name) }}" class="form-control" required></div>
                        <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" value="{{ old('email', $user->email) }}" class="form-control" required></div>
                        <div class="col-md-6"><label class="form-label">Job title</label><input name="job_title" value="{{ old('job_title', $user->job_title) }}" class="form-control"></div>
                        <div class="col-md-6"><label class="form-label">Phone</label><input name="phone" value="{{ old('phone', $user->phone) }}" class="form-control"></div>
                    </div>
                    <div class="card-footer text-end"><button class="btn btn-primary"><i class="bi bi-save"></i> Save</button></div>
                </form>
            </div>
            <div class="card mb-4">
                <div class="card-header"><h3 class="card-title">Change password</h3></div>
                <form method="POST" action="{{ route('profile.password') }}">
                    @csrf @method('PUT')
                    <div class="card-body row g-3">
                        <div class="col-md-4"><label class="form-label">Current password</label><input type="password" name="current_password" class="form-control" required autocomplete="current-password"></div>
                        <div class="col-md-4"><label class="form-label">New password</label><input type="password" name="password" class="form-control" required autocomplete="new-password"></div>
                        <div class="col-md-4"><label class="form-label">Confirm</label><input type="password" name="password_confirmation" class="form-control" required autocomplete="new-password"></div>
                    </div>
                    <div class="card-footer text-end"><button class="btn btn-warning"><i class="bi bi-key"></i> Change password</button></div>
                </form>
            </div>
        </div>
    </div>
@stop
