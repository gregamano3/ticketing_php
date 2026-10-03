<?php

namespace App\Http\Requests;

use App\Models\KbArticle;
use Illuminate\Foundation\Http\FormRequest;

class KbArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $article = $this->route('article');

        return $article ? $this->user()->can('update', $article) : $this->user()->can('create', KbArticle::class);
    }

    public function rules(): array
    {
        return [
            'kb_category_id' => ['required', 'exists:kb_categories,id'],
            'title' => ['required', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'body' => ['required', 'string', 'max:200000'],
            'is_published' => ['nullable', 'boolean'],
        ];
    }
}
