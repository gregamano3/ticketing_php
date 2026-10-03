@extends('layouts.app')

@section('plugins.Quill', true)

@section('page_title', $article->exists ? 'Edit article' : 'New article')

@section('body_content')
    <form method="POST" action="{{ $article->exists ? route('kb.articles.update', $article) : route('kb.articles.store') }}" id="kb-form">
        @csrf
        @if ($article->exists) @method('PUT') @endif
        <div class="row">
            <div class="col-lg-9">
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label" for="title">Title</label>
                            <input type="text" name="title" id="title" value="{{ old('title', $article->title) }}" class="form-control form-control-lg" required maxlength="255">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="excerpt">Summary</label>
                            <input type="text" name="excerpt" id="excerpt" value="{{ old('excerpt', $article->excerpt) }}" class="form-control" maxlength="500" placeholder="One-sentence summary shown in search results">
                        </div>
                        <label class="form-label">Content</label>
                        <div id="editor">{!! clean(old('body', $article->body)) !!}</div>
                        <input type="hidden" name="body" id="body">
                    </div>
                </div>
            </div>
            <div class="col-lg-3">
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label" for="kb_category_id">Category</label>
                            <select name="kb_category_id" id="kb_category_id" class="form-select" required>
                                @foreach ($categories as $c)
                                    <option value="{{ $c->id }}" @selected(old('kb_category_id', $article->kb_category_id) == $c->id)>{{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-check form-switch">
                            <input type="hidden" name="is_published" value="0">
                            <input class="form-check-input" type="checkbox" role="switch" name="is_published" value="1" id="is_published" @checked(old('is_published', $article->is_published))>
                            <label class="form-check-label" for="is_published">Published</label>
                        </div>
                    </div>
                    <div class="card-footer text-end">
                        <a href="{{ $article->exists ? route('kb.articles.show', $article) : route('kb.index') }}" class="btn btn-outline-secondary">Cancel</a>
                        <button class="btn btn-primary"><i class="bi bi-save"></i> Save</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
@stop

@push('js')
<script>
    window.addEventListener('load', () => {
        const quill = new Quill('#editor', {
            theme: 'snow',
            modules: {
                toolbar: [
                    [{ header: [2, 3, 4, false] }],
                    ['bold', 'italic', 'underline', 'strike', 'code'],
                    [{ list: 'ordered' }, { list: 'bullet' }],
                    ['blockquote', 'code-block', 'link', 'image'],
                    ['clean'],
                ],
            },
        });
        document.getElementById('kb-form').addEventListener('submit', () => {
            document.getElementById('body').value = quill.getSemanticHTML();
        });
    });
</script>
@endpush
