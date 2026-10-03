@extends('layouts.app')

@section('plugins.TomSelect', true)

@section('page_title', 'New ticket')
@section('page_subtitle', 'Describe the problem and we will route it to the right team.')

@section('body_content')
    <form method="POST" action="{{ route('tickets.store') }}" enctype="multipart/form-data">
        @csrf
        <div class="row">
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label" for="subject">Subject <span class="text-danger">*</span></label>
                            <input type="text" name="subject" id="subject" value="{{ old('subject') }}" class="form-control @error('subject') is-invalid @enderror" required maxlength="255" autofocus placeholder="Short summary of the issue">
                            @error('subject')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="description">Description <span class="text-danger">*</span></label>
                            <textarea name="description" id="description" rows="10" class="form-control @error('description') is-invalid @enderror" required placeholder="What happened? What did you expect? Steps to reproduce, error messages…">{{ old('description') }}</textarea>
                            @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div>
                            <label class="form-label" for="attachments">Attachments</label>
                            <input type="file" name="attachments[]" id="attachments" class="form-control @error('attachments.*') is-invalid @enderror" multiple>
                            <div class="form-text">Up to {{ config('helpdesk.attachments.max_files') }} files, {{ round(config('helpdesk.attachments.max_kb') / 1024) }} MB each.</div>
                            @error('attachments.*')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card mb-4">
                    <div class="card-header"><h3 class="card-title">Details</h3></div>
                    <div class="card-body">
                        @include('tickets._fields', ['ticket' => new \App\Models\Ticket])
                    </div>
                    <div class="card-footer text-end">
                        <a href="{{ route('tickets.index') }}" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-success"><i class="bi bi-send"></i> Submit ticket</button>
                    </div>
                </div>
                <div class="callout callout-info small">
                    <i class="bi bi-lightbulb"></i> Check the <a href="{{ route('kb.index') }}">knowledge base</a> — your question may already be answered.
                </div>
            </div>
        </div>
    </form>
@stop
