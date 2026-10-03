@props(['ticket', 'state'])
@php
    [$class, $icon, $label] = match ($state) {
        'breached' => ['danger', 'alarm', 'Breached'],
        'at_risk' => ['warning', 'hourglass-split', 'At risk'],
        'paused' => ['secondary', 'pause-circle', 'Paused'],
        'met' => ['success', 'check2-circle', 'Met'],
        default => ['success', 'clock', 'On track'],
    };
    $due = $ticket->due_resolution_at;
@endphp
<span {{ $attributes->merge(['class' => "badge text-bg-{$class}"]) }}
      @if($due) title="Resolution due {{ $due->toDayDateTimeString() }}" data-bs-toggle="tooltip" @endif>
    <i class="bi bi-{{ $icon }}"></i>
    {{ $label }}@if($due && in_array($state, ['ok', 'at_risk', 'breached'])) · {{ $due->diffForHumans(short: true) }}@endif
</span>
