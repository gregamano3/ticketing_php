<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['title', 'body', 'user_id', 'department_id'])]
class CannedResponse extends Model
{
    use HasFactory;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function isShared(): bool
    {
        return $this->user_id === null;
    }

    /** Shared responses plus the user's personal ones. */
    public function scopeAvailableTo(Builder $query, User $user): void
    {
        $query->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', $user->id));
    }
}
