<?php

namespace App\Support;

use Illuminate\Support\Collection;

final class HabitatCompletionThreshold
{
    public const DEFAULT_COUNT = 5;

    public static function requiredCount(string $habitatCode, Collection|array $exceptions): int
    {
        $value = $exceptions instanceof Collection
            ? $exceptions->get($habitatCode)
            : ($exceptions[$habitatCode] ?? null);

        if (is_object($value)) {
            $value = $value->actual_subplot_count ?? null;
        }

        $value = (int) $value;

        return $value >= 1 && $value < self::DEFAULT_COUNT
            ? $value
            : self::DEFAULT_COUNT;
    }
}
