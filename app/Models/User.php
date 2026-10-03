<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'department_id', 'job_title', 'phone', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_assigned_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function requestedTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'requester_id');
    }

    public function assignedTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'assignee_id');
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    public function isAgent(): bool
    {
        return $this->hasRole('agent');
    }

    /** Admins and agents are "staff": they work tickets rather than only raise them. */
    public function isStaff(): bool
    {
        return $this->hasAnyRole(['admin', 'agent']);
    }

    /** First-line triage: admins, plus agents granted the tickets.triage permission. */
    public function canTriage(): bool
    {
        return $this->isAdmin() || ($this->isAgent() && $this->checkPermissionTo('tickets.triage'));
    }

    public function scopeTriagers(Builder $query): void
    {
        $query->active()->where(fn ($q) => $q->role('admin')->orWhere(fn ($a) => $a->role('agent')->permission('tickets.triage')));
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopeAgents(Builder $query): void
    {
        $query->role(['admin', 'agent']);
    }

    /** Initials avatar as an inline SVG, so no user data leaves the app. */
    public function adminlte_image(): string
    {
        $initials = collect(preg_split('/\s+/', trim($this->name)))
            ->filter()->take(2)
            ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');
        $colors = ['#0d6efd', '#6610f2', '#6f42c1', '#d63384', '#dc3545', '#fd7e14', '#198754', '#20c997', '#0dcaf0', '#495057'];
        $color = $colors[crc32($this->email ?? $this->name) % count($colors)];

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 64 64">'
            .'<rect width="64" height="64" fill="'.$color.'"/>'
            .'<text x="50%" y="50%" dy=".35em" text-anchor="middle" fill="#fff" font-family="Arial,sans-serif" font-size="26">'
            .e($initials).'</text></svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    public function adminlte_desc(): string
    {
        return $this->getRoleNames()->map(fn ($r) => ucfirst($r))->implode(', ');
    }

    public function adminlte_profile_url(): string
    {
        return 'profile';
    }
}
