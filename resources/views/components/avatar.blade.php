@props(['user', 'size' => 'sm'])
@if ($user)
    <img src="{{ $user->adminlte_image() }}" alt="{{ $user->name }}" title="{{ $user->name }}"
         {{ $attributes->merge(['class' => "rounded-circle avatar-{$size}"]) }}>
@endif
