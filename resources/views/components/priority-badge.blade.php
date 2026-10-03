@props(['priority'])
@if ($priority)
    <span {{ $attributes->merge(['class' => 'badge rounded-pill border border-'.$priority->color.' text-'.$priority->color.'-emphasis bg-'.$priority->color.'-subtle']) }}>
        <i class="bi bi-flag-fill"></i> {{ $priority->name }}
    </span>
@endif
