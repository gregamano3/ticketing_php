<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['name', 'slug', 'description', 'icon', 'sort_order'])]
class KbCategory extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (KbCategory $c) {
            $c->slug = $c->slug ?: Str::slug($c->name);
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function articles(): HasMany
    {
        return $this->hasMany(KbArticle::class);
    }
}
