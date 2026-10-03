<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Department;
use App\Models\Priority;
use App\Models\Status;
use App\Models\User;
use Spatie\Activitylog\Models\Activity;

/** Turns logged attribute changes (foreign keys) into readable "Field: old → new" lines. */
class ActivityPresenter
{
    private const FIELDS = [
        'status_id' => ['Status', Status::class],
        'priority_id' => ['Priority', Priority::class],
        'assignee_id' => ['Assignee', User::class],
        'department_id' => ['Department', Department::class],
        'category_id' => ['Category', Category::class],
        'subject' => ['Subject', null],
        'escalation_level' => ['Escalation level', null],
        'title' => ['Title', null],
        'is_published' => ['Published', null],
    ];

    private array $names = [];

    /** @return string[] */
    public function changes(Activity $activity): array
    {
        $new = $activity->attribute_changes?->get('attributes') ?? [];
        $old = $activity->attribute_changes?->get('old') ?? [];

        if ($activity->event === 'created') {
            return [];
        }

        $lines = [];
        foreach ($new as $key => $value) {
            [$label, $model] = self::FIELDS[$key] ?? [null, null];
            if (! $label) {
                continue;
            }
            $lines[] = sprintf('%s: %s → %s', $label, $this->display($model, $old[$key] ?? null), $this->display($model, $value));
        }

        return $lines;
    }

    public function icon(Activity $activity): string
    {
        return match ($activity->event) {
            'created' => 'bi bi-plus-circle text-success',
            'replied' => 'bi bi-chat-left-text text-primary',
            'noted' => 'bi bi-lock text-warning',
            'escalated' => 'bi bi-exclamation-triangle text-danger',
            'deleted' => 'bi bi-trash text-danger',
            default => 'bi bi-pencil text-secondary',
        };
    }

    private function display(?string $model, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }
        if (is_bool($value)) {
            return $value ? 'yes' : 'no';
        }
        if (! $model) {
            return (string) $value;
        }

        $this->names[$model] ??= $model::query()->pluck('name', 'id')->all();

        return $this->names[$model][$value] ?? "#{$value}";
    }
}
