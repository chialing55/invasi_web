<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class FloraChartData
{
    /**
     * 歸化物種優勢科 Top-N（以 distinct spcode 計數）
     * 不含unknown
     */
    public static function topNaturalizedFamilies(array $selectedPlots, int $limit = 10): array
    {
        $statusExpr = TaiwanChecklistQuery::statusExpr('s');

        $sub = DB::connection('invasiflora')
            ->table('im_spvptdata_2025 as p')
            ->join('im_splotdata_2025 as e', 'p.plot_full_id', '=', 'e.plot_full_id')
            ->leftJoin('taiwan_checklist as raw', 'p.spcode', '=', 'raw.spcode')
            ->leftJoin('taiwan_checklist as s', 's.spcode', '=', DB::raw(TaiwanChecklistQuery::currentSpcodeExpr('raw', 'p')))
            ->whereIn('e.plot', $selectedPlots)
            ->whereNotNull('p.spcode')
            ->whereRaw("($statusExpr) = 'naturalized'")
            ->selectRaw("
                COALESCE(
                        NULLIF(s.chfamily,''),
                        NULLIF(s.family,'')) AS family,
                NULLIF(TRIM(s.spcode),'')    AS sp
            ");

        $rows = DB::connection('invasiflora')->query()->fromSub($sub, 't')
            ->whereNotNull('sp')
            ->groupBy('family')
            ->selectRaw('family, COUNT(DISTINCT sp) AS n_species')
            ->orderByDesc('n_species')
            ->orderBy('family')
            ->limit($limit)
            ->get()
            ->map(fn($r) => ['植物科名' => $r->family ?? '(未註科)', '物種數' => (int)$r->n_species])
            ->values()
            ->all();

        return [
            'title'    => '歸化物種優勢科 Top ' . $limit,
            'headings' => ['植物科名','物種數'],
            'rows'     => $rows,
        ];
    }
    public static function lowElevationNaturalizedFamilyComparison(array $selectedPlots, float $maxElevation = 500.0, int $limit = 15): array
    {
        $plots = self::eligibleLowElevationPlots($selectedPlots, $maxElevation);
        if (empty($plots)) {
            return [
                'title' => '低海拔外來植物優勢科比較圖',
                'headings' => ['植物科名', '前次調查', '本次調查'],
                'rows' => [],
                'plotCount' => 0,
                'countyLabel' => self::countyLabel($selectedPlots),
            ];
        }

        $previous = collect(self::naturalizedFamilyCounts2010($plots))->keyBy('植物科名');
        $current = collect(self::naturalizedFamilyCounts2025($plots))->keyBy('植物科名');
        $families = $previous->keys()->merge($current->keys())->unique()->values();

        $rows = $families
            ->map(function ($family) use ($previous, $current) {
                return [
                    '植物科名' => (string) $family,
                    '前次調查' => (int) ($previous->get($family)['前次調查'] ?? 0),
                    '本次調查' => (int) ($current->get($family)['本次調查'] ?? 0),
                ];
            })
            ->sort(function ($a, $b) {
                return [$b['前次調查'], $b['本次調查'], $a['植物科名']] <=> [$a['前次調查'], $a['本次調查'], $b['植物科名']];
            })
            ->take($limit)
            ->values()
            ->all();

        return [
            'title' => '低海拔外來植物優勢科比較圖',
            'headings' => ['植物科名', '前次調查', '本次調查'],
            'rows' => $rows,
            'plotCount' => count($plots),
            'countyLabel' => self::countyLabel($plots),
        ];
    }

    private static function eligibleLowElevationPlots(array $selectedPlots, float $maxElevation): array
    {
        $selectedPlots = array_values(array_filter(array_map('strval', $selectedPlots), fn($plot) => $plot !== ''));
        if (empty($selectedPlots)) {
            return [];
        }

        return DB::connection('invasiflora')
            ->table('im_splotdata_2025 as e')
            ->whereIn('e.plot', $selectedPlots)
            ->groupBy('e.plot')
            ->havingRaw('MAX(COALESCE(e.elevation, 99999)) <= ?', [$maxElevation])
            ->orderBy('e.plot')
            ->pluck('e.plot')
            ->map(fn($plot) => (string) $plot)
            ->values()
            ->all();
    }

    private static function naturalizedFamilyCounts2025(array $plots): array
    {
        $statusExpr = TaiwanChecklistQuery::statusExpr('s');

        $sub = DB::connection('invasiflora')
            ->table('im_spvptdata_2025 as p')
            ->join('im_splotdata_2025 as e', 'p.plot_full_id', '=', 'e.plot_full_id')
            ->leftJoin('taiwan_checklist as raw', 'p.spcode', '=', 'raw.spcode')
            ->leftJoin('taiwan_checklist as s', 's.spcode', '=', DB::raw(TaiwanChecklistQuery::currentSpcodeExpr('raw', 'p')))
            ->whereIn('e.plot', $plots)
            ->whereNotNull('p.spcode')
            ->whereRaw("($statusExpr) = 'naturalized'")
            ->selectRaw("COALESCE(NULLIF(s.chfamily,''), NULLIF(s.family,''), '(未註科)') AS family, NULLIF(TRIM(s.spcode),'') AS sp");

        return DB::connection('invasiflora')->query()->fromSub($sub, 't')
            ->whereNotNull('sp')
            ->groupBy('family')
            ->selectRaw('family, COUNT(DISTINCT sp) AS n_species')
            ->get()
            ->map(fn($r) => ['植物科名' => (string) $r->family, '本次調查' => (int) $r->n_species])
            ->values()
            ->all();
    }

    private static function naturalizedFamilyCounts2010(array $plots): array
    {
        $statusExpr = TaiwanChecklistQuery::statusExpr('s');

        $sub = DB::connection('invasiflora')
            ->table('im_spvptdata_2010 as p')
            ->join('im_splotdata_2010 as e', function ($join) {
                $join->on('p.PLOT_ID', '=', 'e.PLOT_ID')
                    ->on('p.HAB_TYPE', '=', 'e.HAB_TYPE')
                    ->on('p.SUB_ID', '=', 'e.SUB_ID');
            })
            ->leftJoin('taiwan_checklist as raw', 'p.spcode', '=', 'raw.spcode')
            ->leftJoin('taiwan_checklist as s', 's.spcode', '=', DB::raw(TaiwanChecklistQuery::currentSpcodeExpr('raw', 'p')))
            ->whereIn('p.PLOT_ID', $plots)
            ->whereNotNull('p.spcode')
            ->whereRaw("($statusExpr) = 'naturalized'")
            ->selectRaw("COALESCE(NULLIF(s.chfamily,''), NULLIF(s.family,''), '(未註科)') AS family, NULLIF(TRIM(s.spcode),'') AS sp");

        return DB::connection('invasiflora')->query()->fromSub($sub, 't')
            ->whereNotNull('sp')
            ->groupBy('family')
            ->selectRaw('family, COUNT(DISTINCT sp) AS n_species')
            ->get()
            ->map(fn($r) => ['植物科名' => (string) $r->family, '前次調查' => (int) $r->n_species])
            ->values()
            ->all();
    }

    private static function countyLabel(array $plots): string
    {
        $plots = array_values(array_filter(array_map('strval', $plots), fn($plot) => $plot !== ''));
        if (empty($plots)) {
            return '選取縣市';
        }

        $counties = DB::connection('invasiflora')
            ->table('plot_list')
            ->whereIn('plot', $plots)
            ->whereNotNull('county')
            ->distinct()
            ->orderBy('county')
            ->pluck('county')
            ->filter()
            ->values()
            ->all();

        if (count($counties) === 1) {
            return (string) $counties[0];
        }

        return count($counties) > 1 ? implode('、', $counties) : '選取縣市';
    }
}
