<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'color', 'is_default', 'is_closed', 'is_resolved', 'pauses_sla', 'sort_order'])]
class Status extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_closed' => 'boolean',
            'is_resolved' => 'boolean',
            'pauses_sla' => 'boolean',
        ];
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('id');
    }

    /** A status that ends the ticket's lifecycle (resolved or closed). */
    public function isFinal(): bool
    {
        return $this->is_closed || $this->is_resolved;
    }

    public static function default(): ?self
    {
        return static::where('is_default', true)->first() ?? static::ordered()->first();
    }
}
