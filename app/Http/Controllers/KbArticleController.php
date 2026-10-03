<?php

namespace App\Http\Controllers;

use App\Http\Requests\KbArticleRequest;
use App\Models\KbArticle;
use App\Models\KbCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Mews\Purifier\Facades\Purifier;

class KbArticleController extends Controller
{
    /** Knowledge base home: categories, search and popular articles. */
    public function index(Request $request): View
    {
        $canManage = $request->user()->can('kb.manage');
        $q = trim((string) $request->query('q'));

        $visible = fn ($query) => $query->when(! $canManage, fn ($a) => $a->published());

        return view('kb.index', [
            'q' => $q,
            'categories' => KbCategory::withCount(['articles' => $visible])->orderBy('sort_order')->orderBy('name')->get(),
            'results' => $q !== '' ? $visible(KbArticle::query())->search($q)->with('category')->paginate(15)->withQueryString() : null,
            'popular' => KbArticle::published()->orderByDesc('views')->limit(6)->get(),
            'drafts' => $canManage ? KbArticle::where('is_published', false)->latest()->limit(6)->get() : collect(),
        ]);
    }

    public function category(Request $request, KbCategory $category): View
    {
        $articles = $category->articles()
            ->when(! $request->user()->can('kb.manage'), fn ($q) => $q->published())
            ->with('author')->orderBy('title')->paginate(20);

        return view('kb.category', compact('category', 'articles'));
    }

    public function show(KbArticle $article): View
    {
        $this->authorize('view', $article);

        if ($article->is_published) {
            $article->increment('views');
        }

        $related = KbArticle::published()->where('kb_category_id', $article->kb_category_id)
            ->whereKeyNot($article->id)->orderByDesc('views')->limit(5)->get();

        return view('kb.show', ['article' => $article->load(['category', 'author']), 'related' => $related]);
    }

    public function create(): View
    {
        $this->authorize('create', KbArticle::class);

        return view('kb.form', ['article' => new KbArticle(['is_published' => true]), 'categories' => KbCategory::orderBy('name')->get()]);
    }

    public function store(KbArticleRequest $request): RedirectResponse
    {
        $article = KbArticle::create([
            ...$this->payload($request),
            'author_id' => $request->user()->id,
        ]);

        return redirect()->route('kb.articles.show', $article)->with('success', 'Article created.');
    }

    public function edit(KbArticle $article): View
    {
        $this->authorize('update', $article);

        return view('kb.form', ['article' => $article, 'categories' => KbCategory::orderBy('name')->get()]);
    }

    public function update(KbArticleRequest $request, KbArticle $article): RedirectResponse
    {
        $article->update($this->payload($request));

        return redirect()->route('kb.articles.show', $article)->with('success', 'Article updated.');
    }

    public function destroy(KbArticle $article): RedirectResponse
    {
        $this->authorize('delete', $article);
        $article->delete();

        return redirect()->route('kb.index')->with('success', 'Article deleted.');
    }

    public function vote(Request $request, KbArticle $article): RedirectResponse
    {
        $this->authorize('view', $article);
        $key = 'kb_voted_'.$article->id;

        if (! $request->session()->has($key)) {
            $article->increment($request->boolean('helpful') ? 'helpful_yes' : 'helpful_no');
            $request->session()->put($key, true);
        }

        return back()->with('success', 'Thanks for your feedback!');
    }

    private function payload(KbArticleRequest $request): array
    {
        return [
            'kb_category_id' => $request->validated('kb_category_id'),
            'title' => $request->validated('title'),
            'excerpt' => $request->validated('excerpt'),
            'body' => Purifier::clean($request->validated('body')),
            'is_published' => $request->boolean('is_published'),
        ];
    }
}
