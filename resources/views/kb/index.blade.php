@extends('layouts.app')

@section('page_title', 'Knowledge base')
@section('page_subtitle', 'Guides and answers to common questions')

@section('page_actions')
    @can('create', \App\Models\KbArticle::class)
        <a href="{{ route('kb.articles.create') }}" class="btn btn-primary"><i class="bi bi-plus-circle"></i> New article</a>
    @endcan
@stop

@section('body_content')
    <form method="GET" action="{{ route('kb.index') }}" class="mb-4">
        <div class="input-group input-group-lg">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="search" name="q" value="{{ $q }}" class="form-control" placeholder="Search articles, e.g. &quot;reset password&quot; or vpn -windows">
            <button class="btn btn-primary">Search</button>
        </div>
    </form>

    @if ($results)
        <div class="card mb-4">
            <div class="card-header"><h3 class="card-title">{{ $results->total() }} result(s) for “{{ $q }}”</h3></div>
            <div class="list-group list-group-flush">
                @forelse ($results as $article)
                    <a href="{{ route('kb.articles.show', $article) }}" class="list-group-item list-group-item-action">
                        <div class="d-flex justify-content-between">
                            <strong>{{ $article->title }}</strong>
                            <span class="small text-body-secondary">{{ $article->category?->name }}</span>
                        </div>
                        <div class="small text-body-secondary">{{ $article->excerpt ?: \Illuminate\Support\Str::limit(strip_tags($article->body), 160) }}</div>
                        @unless ($article->is_published)<span class="badge text-bg-secondary">Draft</span>@endunless
                    </a>
                @empty
                    <div class="list-group-item text-body-secondary">No articles found. <a href="{{ route('tickets.create') }}">Open a ticket</a> instead.</div>
                @endforelse
            </div>
            @if ($results->hasPages())<div class="card-footer">{{ $results->links() }}</div>@endif
        </div>
    @endif

    <div class="row">
        @foreach ($categories as $category)
            <div class="col-md-6 col-xl-4">
                <a href="{{ route('kb.category', $category) }}" class="card mb-4 text-decoration-none">
                    <div class="card-body d-flex gap-3">
                        <i class="{{ $category->icon }} fs-1 text-primary"></i>
                        <div>
                            <h5 class="mb-1 text-body-emphasis">{{ $category->name }}</h5>
                            <div class="small text-body-secondary">{{ $category->description }}</div>
                            <div class="small mt-1">{{ $category->articles_count }} {{ \Illuminate\Support\Str::plural('article', $category->articles_count) }}</div>
                        </div>
                    </div>
                </a>
            </div>
        @endforeach
    </div>

    <div class="row">
        <div class="col-lg-{{ $drafts->isNotEmpty() ? 6 : 12 }}">
            <div class="card mb-4">
                <div class="card-header"><h3 class="card-title"><i class="bi bi-star"></i> Popular articles</h3></div>
                <div class="list-group list-group-flush">
                    @forelse ($popular as $article)
                        <a href="{{ route('kb.articles.show', $article) }}" class="list-group-item list-group-item-action d-flex justify-content-between">
                            {{ $article->title }} <span class="small text-body-secondary"><i class="bi bi-eye"></i> {{ $article->views }}</span>
                        </a>
                    @empty
                        <div class="list-group-item text-body-secondary">No articles yet.</div>
                    @endforelse
                </div>
            </div>
        </div>
        @if ($drafts->isNotEmpty())
            <div class="col-lg-6">
                <div class="card mb-4">
                    <div class="card-header"><h3 class="card-title"><i class="bi bi-pencil-square"></i> Drafts</h3></div>
                    <div class="list-group list-group-flush">
                        @foreach ($drafts as $article)
                            <a href="{{ route('kb.articles.edit', $article) }}" class="list-group-item list-group-item-action">{{ $article->title }}</a>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
    </div>
@stop
