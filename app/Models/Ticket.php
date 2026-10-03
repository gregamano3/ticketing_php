<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable([
    'reference', 'subject', 'description', 'requester_id', 'assignee_id', 'department_id',
    'category_id', 'priority_id', 'status_id', 'source', 'due_response_at', 'due_resolution_at',
    'first_responded_at', 'sla_paused_at', 'resolved_at', 'closed_at', 'response_breached', 'resolution_breached',
    'escalation_level',
])]
class Ticket extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected function casts(): array
    {
        return [
            'requester_id' => 'integer',
            'assignee_id' => 'integer',
            'department_id' => 'integer',
            'category_id' => 'integer',
            'priority_id' => 'integer',
            'status_id' => 'integer',
            'escalation_level' => 'integer',
            'due_response_at' => 'datetime',
            'due_resolution_at' => 'datetime',
            'first_responded_at' => 'datetime',
            'sla_paused_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
            'response_breached' => 'boolean',
            'resolution_breached' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['subject', 'assignee_id', 'department_id', 'category_id', 'priority_id', 'status_id', 'escalation_level'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('ticket');
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function priority(): BelongsTo
    {
        return $this->belongsTo(Priority::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    public function replies(): HasMany
    {
        return $this->hasMany(TicketReply::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function watchers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'ticket_watchers');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function escalations(): HasMany
    {
        return $this->hasMany(SlaEscalation::class);
    }

    /** Everyone who should hear about changes to this ticket. */
    public function participants()
    {
        return collect([$this->requester, $this->assignee])
            ->merge($this->watchers)
            ->filter()
            ->unique('id')
            ->values();
    }

    public function isOpen(): bool
    {
        return ! $this->status?->isFinal();
    }

    public function isOverdue(): bool
    {
        return $this->isOpen() && $this->due_resolution_at && $this->due_resolution_at->isPast();
    }

    public function isBreached(): bool
    {
        return $this->response_breached || $this->resolution_breached;
    }

    public function scopeOpen(Builder $query): void
    {
        $query->whereHas('status', fn ($q) => $q->where('is_closed', false)->where('is_resolved', false));
    }

    public function scopeOverdue(Builder $query): void
    {
        $query->open()->where('due_resolution_at', '<', now());
    }

    public function scopeUnassigned(Builder $query): void
    {
        $query->whereNull('assignee_id');
    }

    /** Restrict to the tickets a user is allowed to see. */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->isAdmin()) {
            return;
        }

        $query->where(function (Builder $q) use ($user) {
            $q->where('requester_id', $user->id)
                ->orWhere('assignee_id', $user->id)
                ->orWhereHas('watchers', fn ($w) => $w->where('users.id', $user->id));

            if ($user->isAgent() && $user->department_id) {
                $q->orWhere('department_id', $user->department_id);
            }
        });
    }

    /** PostgreSQL full-text search with a prefix-match fallback on the reference. */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $query->where(function (Builder $q) use ($term) {
            $q->whereRaw("search_vector @@ websearch_to_tsquery('english', ?)", [$term])
                ->orWhere('reference', 'ilike', $term.'%');
        });
    }
}
