@extends('layouts.app')

@section('page_title', $article->title)
@section('page_subtitle')
    <a href="{{ route('kb.category', $article->category) }}">{{ $article->category->name }}</a>
    · by {{ $article->author?->name }} · updated {{ $article->updated_at->diffForHumans() }} · {{ $article->views }} views
    @unless ($article->is_published)<span class="badge text-bg-secondary">Draft</span>@endunless
@stop

@section('page_actions')
    @can('update', $article)
        <a href="{{ route('kb.articles.edit', $article) }}" class="btn btn-outline-secondary"><i class="bi bi-pencil"></i> Edit</a>
    @endcan
    @can('delete', $article)
        <form method="POST" action="{{ route('kb.articles.destroy', $article) }}" class="d-inline" onsubmit="return confirm('Delete this article?')">
            @csrf @method('DELETE')
            <button class="btn btn-outline-danger"><i class="bi bi-trash"></i></button>
        </form>
    @endcan
@stop

@section('body_content')
    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-body kb-body">
                    @if ($article->excerpt)<p class="lead">{{ $article->excerpt }}</p>@endif
                    {{-- Body is sanitized with HTMLPurifier on save. --}}
                    {!! $article->body !!}
                </div>
                <div class="card-footer d-flex flex-wrap align-items-center gap-2">
                    <span>Was this article helpful?</span>
                    @foreach ([1 => ['hand-thumbs-up', 'success', 'Yes', $article->helpful_yes], 0 => ['hand-thumbs-down', 'danger', 'No', $article->helpful_no]] as $value => [$icon, $color, $label, $count])
                        <form method="POST" action="{{ route('kb.articles.vote', $article) }}">
                            @csrf <input type="hidden" name="helpful" value="{{ $value }}">
                            <button class="btn btn-sm btn-outline-{{ $color }}"><i class="bi bi-{{ $icon }}"></i> {{ $label }} ({{ $count }})</button>
                        </form>
                    @endforeach
                    <span class="ms-auto small">Still stuck? <a href="{{ route('tickets.create') }}">Open a ticket</a></span>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header"><h3 class="card-title">Related articles</h3></div>
                <div class="list-group list-group-flush">
                    @forelse ($related as $r)
                        <a href="{{ route('kb.articles.show', $r) }}" class="list-group-item list-group-item-action">{{ $r->title }}</a>
                    @empty
                        <div class="list-group-item text-body-secondary small">No related articles.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@stop
