@props(['tags'])
@foreach ($tags as $tag)
    <span class="badge text-bg-{{ $tag->color }} fw-normal"><i class="bi bi-tag"></i> {{ $tag->name }}</span>
@endforeach
