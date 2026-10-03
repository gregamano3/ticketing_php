@extends('layouts.app')

@section('page_title', 'Notifications')

@section('page_actions')
    <form method="POST" action="{{ route('notifications.read-all') }}">
        @csrf
        <button class="btn btn-outline-secondary"><i class="bi bi-check2-all"></i> Mark all as read</button>
    </form>
@stop

@section('body_content')
    <div class="card">
        <div class="list-group list-group-flush">
            @forelse ($notifications as $n)
                <a href="{{ route('notifications.read', $n->id) }}" class="list-group-item list-group-item-action notification-item {{ $n->read_at ? '' : 'unread' }}">
                    <div class="d-flex gap-3 align-items-center">
                        <i class="{{ $n->data['icon'] ?? 'bi bi-bell' }} text-{{ $n->data['color'] ?? 'primary' }} fs-4"></i>
                        <div class="flex-grow-1">
                            <div class="{{ $n->read_at ? '' : 'fw-semibold' }}">{{ $n->data['message'] ?? '' }}</div>
                            <div class="small text-body-secondary">{{ $n->created_at->diffForHumans() }}</div>
                        </div>
                        @unless ($n->read_at)<span class="badge text-bg-primary">New</span>@endunless
                    </div>
                </a>
            @empty
                <div class="list-group-item text-center text-body-secondary py-5"><i class="bi bi-bell-slash fs-2 d-block"></i> No notifications.</div>
            @endforelse
        </div>
        @if ($notifications->hasPages())<div class="card-footer">{{ $notifications->links() }}</div>@endif
    </div>
@stop
