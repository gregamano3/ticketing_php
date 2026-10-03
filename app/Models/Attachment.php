<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

#[Fillable(['user_id', 'disk', 'path', 'original_name', 'mime', 'size'])]
class Attachment extends Model
{
    protected static function booted(): void
    {
        static::deleted(fn (Attachment $a) => Storage::disk($a->disk)->delete($a->path));
    }

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The ticket this attachment ultimately belongs to (directly or via a reply). */
    public function ticket(): ?Ticket
    {
        $parent = $this->attachable;

        return $parent instanceof TicketReply ? $parent->ticket : $parent;
    }

    public static function storeFor(Model $attachable, UploadedFile $file, User $user): self
    {
        $path = $file->store('attachments/'.now()->format('Y/m'), 'local');

        return $attachable->attachments()->create([
            'user_id' => $user->id,
            'disk' => 'local',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getClientMimeType(),
            'size' => $file->getSize(),
        ]);
    }

    public function getHumanSizeAttribute(): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $this->size;
        $i = 0;
        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }

        return round($size, 1).' '.$units[$i];
    }

    public function getIconAttribute(): string
    {
        return match (true) {
            str_starts_with((string) $this->mime, 'image/') => 'bi bi-file-earmark-image',
            $this->mime === 'application/pdf' => 'bi bi-file-earmark-pdf',
            str_contains((string) $this->mime, 'zip') => 'bi bi-file-earmark-zip',
            str_contains((string) $this->mime, 'sheet') || str_contains((string) $this->mime, 'excel') => 'bi bi-file-earmark-spreadsheet',
            str_contains((string) $this->mime, 'word') => 'bi bi-file-earmark-word',
            default => 'bi bi-file-earmark',
        };
    }
}
