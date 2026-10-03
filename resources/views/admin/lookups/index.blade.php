@extends('layouts.app')

@section('page_title')
    <i class="{{ $def['icon'] }}"></i> {{ $def['title'] }}
@stop
@section('title', $def['title'])

@section('page_actions')
    <a href="{{ route('admin.lookups.create', $type) }}" class="btn btn-primary"><i class="bi bi-plus-circle"></i> New {{ $def['singular'] }}</a>
@stop

@section('body_content')
    <div class="card">
        <div class="card-body p-0 table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        @foreach ($def['columns'] as $label)<th>{{ $label }}</th>@endforeach
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($items as $item)
                    <tr>
                        @foreach ($def['columns'] as $key => $label)
                            @php $value = data_get($item, $key); @endphp
                            <td>
                                @if ($key === 'color')
                                    <span class="badge text-bg-{{ $value }}">{{ $value }}</span>
                                @elseif ($key === 'icon')
                                    <i class="{{ $value }} fs-5"></i>
                                @elseif (is_bool($value))
                                    {!! $value ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-dash text-body-secondary"></i>' !!}
                                @else
                                    {{ $value ?? '—' }}
                                @endif
                            </td>
                        @endforeach
                        <td class="text-end text-nowrap">
                            <a href="{{ route('admin.lookups.edit', [$type, $item->id]) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                            <form method="POST" action="{{ route('admin.lookups.destroy', [$type, $item->id]) }}" class="d-inline" onsubmit="return confirm('Delete this {{ $def['singular'] }}?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="{{ count($def['columns']) + 1 }}" class="text-center text-body-secondary py-4">Nothing here yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@stop
