@props(['status'])
@if ($status)
    <span {{ $attributes->merge(['class' => 'badge text-bg-'.$status->color]) }}>{{ $status->name }}</span>
@endif
