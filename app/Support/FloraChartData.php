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
        $statusExpr = "
        CASE
        WHEN COALESCE(s.naturalized, 0) = 1 THEN 'naturalized'
        WHEN COALESCE(s.cultivated, 0) = 1 
         AND COALESCE(s.naturalized, 0) != 1 THEN 'cultivated'
        WHEN COALESCE(s.uncertain  , 0) = 1 THEN 'uncertain'
        ELSE 'native'
        END";

        $sub = DB::connection('invasiflora')
            ->table('im_spvptdata_2025 as p')
            ->join('im_splotdata_2025 as e', 'p.plot_full_id', '=', 'e.plot_full_id')
            ->join('spinfo as s', 'p.spcode', '=', 's.spcode')
            ->whereIn('e.plot', $selectedPlots)
            ->whereNotNull('p.spcode')
            ->whereRaw("($statusExpr) = 'naturalized'")
            ->selectRaw("
                COALESCE(
                        NULLIF(s.chfamily,''),
                        NULLIF(s.family,'')) AS family,
                NULLIF(TRIM(p.spcode),'')    AS sp
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
}
