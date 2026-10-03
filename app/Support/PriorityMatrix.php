<?php

namespace App\Support;

use App\Models\Priority;
use Illuminate\Support\Collection;

/**
 * Suggests a priority from the requester's impact and urgency (ITIL style),
 * so requesters describe the situation instead of picking "Urgent".
 */
class PriorityMatrix
{
    public const IMPACT = [
        1 => 'Just me',
        2 => 'My team',
        3 => 'Whole department or company',
    ];

    public const URGENCY = [
        1 => 'Low — it can wait',
        2 => 'Normal — it slows me down',
        3 => 'High — I am blocked',
    ];

    /** Score (impact + urgency, 2..6) → rank among priorities ordered by level. */
    private const RANK = [2 => 0, 3 => 1, 4 => 2, 5 => 2, 6 => 3];

    public static function suggest(?int $impact, ?int $urgency, ?Collection $priorities = null): ?Priority
    {
        if (! $impact || ! $urgency) {
            return null;
        }

        $priorities ??= Priority::orderBy('level')->get();
        if ($priorities->isEmpty()) {
            return null;
        }

        $rank = self::RANK[$impact + $urgency] ?? 1;

        return $priorities->values()->get(min($rank, $priorities->count() - 1));
    }

    /** The more urgent of two priorities (either may be null). */
    public static function max(?Priority $a, ?Priority $b): ?Priority
    {
        if (! $a || ! $b) {
            return $a ?? $b;
        }

        return $a->level >= $b->level ? $a : $b;
    }
}
