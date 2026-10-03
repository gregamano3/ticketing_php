@extends('adminlte::page')

@section('title')@yield('page_title')@stop

@section('content_header')
    <div class="row align-items-center">
        <div class="col-sm-7">
            <h3 class="mb-0">@yield('page_title')</h3>
            @hasSection('page_subtitle')
                <div class="text-body-secondary small">@yield('page_subtitle')</div>
            @endif
        </div>
        <div class="col-sm-5 text-sm-end mt-2 mt-sm-0">
            @yield('page_actions')
        </div>
    </div>
@stop

@section('content_top_nav_right')
    {{-- The badge must be rendered (even empty) for the polling script to update it. --}}
    <x-adminlte-navbar-notification id="navbar-notifications" icon="bi bi-bell" badge-color="danger"
        :badge-label="auth()->user()->unreadNotifications()->count() ?: ''"
        :update-cfg="['route' => 'notifications.poll', 'period' => 30]" enable-dropdown-mode
        dropdown-footer-label="See all notifications" href="{{ route('notifications.index') }}" />
@stop

@section('content')
    @include('partials.flash')
    @yield('body_content')
@stop

@section('footer')
    <div class="float-end d-none d-sm-inline small text-body-secondary">Internal Helpdesk</div>
    <strong>&copy; {{ date('Y') }} {{ config('app.name') }}</strong>
@stop

@push('css')
<style>
    .ticket-subject { font-weight: 600; }
    .timeline-internal .timeline-item { border-left: 3px solid var(--bs-warning); background: rgba(var(--bs-warning-rgb), .08); }
    .reply-body { white-space: pre-wrap; word-wrap: break-word; }
    .kb-body img { max-width: 100%; height: auto; }
    .avatar-sm { width: 32px; height: 32px; }
    .avatar-xs { width: 22px; height: 22px; }
    .table-tickets td { vertical-align: middle; }
    .filter-bar .form-select, .filter-bar .form-control { min-width: 0; }
    .ql-editor { min-height: 260px; }
    .notification-item.unread { background: rgba(var(--bs-primary-rgb), .06); }
</style>
@endpush
