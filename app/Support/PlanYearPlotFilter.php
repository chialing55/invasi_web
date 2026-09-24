<?php

namespace App\Support;

use App\Models\PlotList2025;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class PlanYearPlotFilter
{
    public static function query(User $user, string|int|null $year = null): Builder
    {
        return PlotList2025::query()
            ->when($year !== null && $year !== '', fn (Builder $query) => $query->where('census_year', $year)
            )
            ->when($user->role !== 'admin', fn (Builder $query) => $query->where('team', (string) $user->organization)
            );
    }

    public static function years(User $user): array
    {
        return self::query($user)
            ->where('census_year', '>=', 2025)
            ->distinct()
            ->orderByDesc('census_year')
            ->pluck('census_year')
            ->map(fn ($year) => (string) $year)
            ->all();
    }

    public static function defaultYear(array $years, ?int $currentYear = null): string
    {
        $years = array_map('strval', $years);
        $currentYear = (string) ($currentYear ?? (int) date('Y'));

        return in_array($currentYear, $years, true)
            ? $currentYear
            : (string) ($years[0] ?? '');
    }

    public static function counties(User $user, string|int $year): array
    {
        return self::query($user, $year)
            ->distinct()
            ->orderBy('county')
            ->pluck('county')
            ->filter()
            ->values()
            ->all();
    }

    public static function plots(User $user, string|int $year, string $county): array
    {
        return self::query($user, $year)
            ->where('county', $county)
            ->distinct()
            ->orderBy('plot')
            ->pluck('plot')
            ->map(fn ($plot) => (string) $plot)
            ->all();
    }
}
