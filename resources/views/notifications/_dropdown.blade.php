@forelse ($notifications as $n)
    <a href="{{ route('notifications.read', $n->id) }}" class="dropdown-item text-wrap">
        <i class="{{ $n->data['icon'] ?? 'bi bi-bell' }} text-{{ $n->data['color'] ?? 'primary' }} me-2"></i>
        <span class="small">{{ $n->data['message'] ?? '' }}</span>
        <span class="float-end text-body-secondary small">{{ $n->created_at->diffForHumans(short: true) }}</span>
    </a>
    <div class="dropdown-divider"></div>
@empty
    <span class="dropdown-item text-body-secondary small">You're all caught up.</span>
@endforelse
