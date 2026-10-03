@extends('layouts.app')

@section('plugins.TomSelect', true)

@section('page_title', 'Edit '.$ticket->reference)

@section('body_content')
    <form method="POST" action="{{ route('tickets.update', $ticket) }}">
        @csrf @method('PUT')
        <div class="row">
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label" for="subject">Subject</label>
                            <input type="text" name="subject" id="subject" value="{{ old('subject', $ticket->subject) }}" class="form-control" required maxlength="255">
                        </div>
                        <div>
                            <label class="form-label" for="description">Description</label>
                            <textarea name="description" id="description" rows="12" class="form-control" required>{{ old('description', $ticket->description) }}</textarea>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card mb-4">
                    <div class="card-body">@include('tickets._fields')</div>
                    <div class="card-footer text-end">
                        <a href="{{ route('tickets.show', $ticket) }}" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Save</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
@stop
