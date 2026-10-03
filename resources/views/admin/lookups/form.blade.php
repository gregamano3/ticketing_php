@extends('layouts.app')

@section('page_title', ($item->exists ? 'Edit ' : 'New ').$def['singular'])

@section('body_content')
    <form method="POST" action="{{ $item->exists ? route('admin.lookups.update', [$type, $item->id]) : route('admin.lookups.store', $type) }}">
        @csrf
        @if ($item->exists) @method('PUT') @endif
        <div class="card" style="max-width: 760px">
            <div class="card-body">
                @foreach ($def['fields'] as $name => $field)
                    @php $value = old($name, $item->{$name}); @endphp
                    <div class="mb-3">
                        @if ($field['type'] === 'checkbox')
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" name="{{ $name }}" value="1" id="{{ $name }}" @checked($value)>
                                <label class="form-check-label" for="{{ $name }}">{{ $field['label'] }}</label>
                            </div>
                        @else
                            <label class="form-label" for="{{ $name }}">{{ $field['label'] }}</label>
                            @switch($field['type'])
                                @case('textarea')
                                    <textarea name="{{ $name }}" id="{{ $name }}" rows="3" class="form-control @error($name) is-invalid @enderror">{{ $value }}</textarea>
                                    @break
                                @case('select')
                                    <select name="{{ $name }}" id="{{ $name }}" class="form-select @error($name) is-invalid @enderror">
                                        <option value="">—</option>
                                        @foreach (($field['options'])($item->id) as $id => $optLabel)
                                            <option value="{{ $id }}" @selected($value == $id)>{{ $optLabel }}</option>
                                        @endforeach
                                    </select>
                                    @break
                                @case('color')
                                    <select name="{{ $name }}" id="{{ $name }}" class="form-select @error($name) is-invalid @enderror">
                                        @foreach (\App\Support\Lookups::COLORS as $color)
                                            <option value="{{ $color }}" @selected($value === $color)>{{ ucfirst($color) }}</option>
                                        @endforeach
                                    </select>
                                    @break
                                @default
                                    <input type="{{ $field['type'] }}" name="{{ $name }}" id="{{ $name }}" value="{{ $value }}" class="form-control @error($name) is-invalid @enderror">
                            @endswitch
                            @error($name)<div class="invalid-feedback">{{ $message }}</div>@enderror
                        @endif
                        @isset($field['help'])<div class="form-text">{{ $field['help'] }}</div>@endisset
                    </div>
                @endforeach
            </div>
            <div class="card-footer text-end">
                <a href="{{ route('admin.lookups.index', $type) }}" class="btn btn-outline-secondary">Cancel</a>
                <button class="btn btn-primary"><i class="bi bi-save"></i> Save</button>
            </div>
        </div>
    </form>
@stop
