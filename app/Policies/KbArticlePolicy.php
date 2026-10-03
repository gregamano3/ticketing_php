<?php

namespace App\Policies;

use App\Models\KbArticle;
use App\Models\User;

class KbArticlePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, KbArticle $article): bool
    {
        return $article->is_published || $user->can('kb.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('kb.manage');
    }

    public function update(User $user, KbArticle $article): bool
    {
        return $user->can('kb.manage');
    }

    public function delete(User $user, KbArticle $article): bool
    {
        return $user->can('kb.manage') && $article->author_id === $user->id;
    }
}
