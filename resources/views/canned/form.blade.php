@extends('layouts.app')

@section('page_title', $response->exists ? 'Edit canned reply' : 'New canned reply')

@section('body_content')
    <form method="POST" action="{{ $response->exists ? route('canned-responses.update', $response) : route('canned-responses.store') }}">
        @csrf
        @if ($response->exists) @method('PUT') @endif
        <div class="card" style="max-width: 820px">
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label" for="title">Title</label>
                    <input type="text" name="title" id="title" value="{{ old('title', $response->title) }}" class="form-control" required maxlength="255">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="body">Body</label>
                    <textarea name="body" id="body" rows="8" class="form-control" required>{{ old('body', $response->body) }}</textarea>
                    <div class="form-text">Use <code>{requester}</code>, <code>{agent}</code> and <code>{reference}</code> as placeholders.</div>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="department_id">Department (optional)</label>
                        <select name="department_id" id="department_id" class="form-select">
                            <option value="">— Any —</option>
                            @foreach ($departments as $d)
                                <option value="{{ $d->id }}" @selected(old('department_id', $response->department_id) == $d->id)>{{ $d->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @can('canned.manage-shared')
                        <div class="col-md-6 d-flex align-items-end">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" name="shared" value="1" id="shared" @checked(old('shared', $response->exists && $response->isShared()))>
                                <label class="form-check-label" for="shared">Shared with all agents</label>
                            </div>
                        </div>
                    @endcan
                </div>
            </div>
            <div class="card-footer text-end">
                <a href="{{ route('canned-responses.index') }}" class="btn btn-outline-secondary">Cancel</a>
                <button class="btn btn-primary"><i class="bi bi-save"></i> Save</button>
            </div>
        </div>
    </form>
@stop
