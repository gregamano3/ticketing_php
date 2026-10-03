@extends('layouts.app')

@section('page_title')
    <i class="{{ $category->icon }}"></i> {{ $category->name }}
@stop
@section('title', $category->name.' · Knowledge base')
@section('page_subtitle', $category->description)

@section('page_actions')
    <a href="{{ route('kb.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Knowledge base</a>
@stop

@section('body_content')
    <div class="card">
        <div class="list-group list-group-flush">
            @forelse ($articles as $article)
                <a href="{{ route('kb.articles.show', $article) }}" class="list-group-item list-group-item-action">
                    <div class="d-flex justify-content-between">
                        <strong>{{ $article->title }}</strong>
                        <span class="small text-body-secondary"><i class="bi bi-eye"></i> {{ $article->views }}</span>
                    </div>
                    <div class="small text-body-secondary">{{ $article->excerpt ?: \Illuminate\Support\Str::limit(strip_tags($article->body), 160) }}</div>
                    @unless ($article->is_published)<span class="badge text-bg-secondary">Draft</span>@endunless
                </a>
            @empty
                <div class="list-group-item text-body-secondary">No articles in this category yet.</div>
            @endforelse
        </div>
        @if ($articles->hasPages())<div class="card-footer">{{ $articles->links() }}</div>@endif
    </div>
@stop
