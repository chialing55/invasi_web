<?php

namespace App\Helpers;

use App\Models\HabitatInfo;
use App\Models\PlotList2025;
use App\Models\SubPlotPlant2010;
use App\Models\SubPlotPlant2025;
use App\Support\HabitatCode;

class PlantStatHelper
{
    public static function summarizeByCountyAndHabitat(string|array $spcode): array
    {
        $spcodes = array_values(array_filter((array) $spcode, fn ($code) => ! blank($code)));

        if (empty($spcodes)) {
            return [];
        }

        $plotCounty = PlotList2025::get(['plot', 'county'])->keyBy('plot')->toArray();
        $habTypeMap = HabitatInfo::get(['habitat_code', 'habitat'])
            ->mapWithKeys(fn ($item) => [HabitatCode::mapKey($item->habitat_code) => $item->habitat]);

        // 只讓資料庫回傳「樣區 × 生育地」的小型彙總，不載入每一筆植物紀錄。
        $rows2010 = SubPlotPlant2010::query()
            ->whereIn('spcode', $spcodes)
            ->selectRaw('PLOT_ID AS plot, HAB_TYPE AS habitat_code')
            ->selectRaw('COUNT(*) AS observation_count')
            ->selectRaw('COUNT(DISTINCT SUB_ID) AS subplot_count')
            ->selectRaw('SUM(COALESCE(CAST(COV AS DECIMAL(18,6)), 0)) AS coverage_sum')
            ->selectRaw('SUM(POW(COALESCE(CAST(COV AS DECIMAL(18,6)), 0), 2)) AS coverage_square_sum')
            ->groupBy('PLOT_ID', 'HAB_TYPE')
            ->toBase()
            ->get();

        $rows2025 = SubPlotPlant2025::query()
            ->whereIn('spcode', $spcodes)
            ->selectRaw('LEFT(plot_full_id, 6) AS plot, SUBSTRING(plot_full_id, 7, 2) AS habitat_code')
            ->selectRaw('COUNT(*) AS observation_count')
            ->selectRaw('COUNT(DISTINCT plot_full_id) AS subplot_count')
            ->selectRaw('SUM(COALESCE(coverage, 0)) AS coverage_sum')
            ->selectRaw('SUM(POW(COALESCE(coverage, 0), 2)) AS coverage_square_sum')
            ->groupByRaw('LEFT(plot_full_id, 6), SUBSTRING(plot_full_id, 7, 2)')
            ->toBase()
            ->get();

        $group2010 = self::combineDatabaseAggregates($rows2010, $plotCounty);
        $group2025 = self::combineDatabaseAggregates($rows2025, $plotCounty);
        $allKeys = $group2010->keys()->merge($group2025->keys())->unique();

        return $allKeys->map(function (string $key) use ($group2010, $group2025, $habTypeMap) {
            $data2010 = $group2010->get($key);
            $data2025 = $group2025->get($key);
            $county = $data2010['county'] ?? $data2025['county'];
            $habitatKey = $data2010['habitat_key'] ?? $data2025['habitat_key'];
            $habType = HabitatCode::codeFromMapKey($habitatKey);
            [$p10, $s10, $m10, $sd10, $plots10] = self::formatAggregate($data2010);
            [$p25, $s25, $m25, $sd25, $plots25] = self::formatAggregate($data2025);

            return [
                'county' => $county,
                'habitat' => $habTypeMap[HabitatCode::mapKey($habType)] ?? $habType,
                'hab_code' => $habType,
                'plot_2010' => $p10,
                'sub_2010' => $s10,
                'cov_sd_2010' => $s10 > 1 ? "{$m10}±{$sd10}" : "{$m10}",
                'cov_2010' => number_format($m10, 2),
                'sd_2010' => $s10 > 1 ? "±{$sd10}" : '',
                'plots_2010' => $plots10,
                'plot_2025' => $p25,
                'sub_2025' => $s25,
                'cov_sd_2025' => $s25 > 1 ? "{$m25}±{$sd25}" : "{$m25}",
                'cov_2025' => number_format($m25, 2),
                'sd_2025' => $s25 > 1 ? "±{$sd25}" : '',
                'plots_2025' => $plots25,
            ];
        })->sortBy(['county', 'habitat'])->values()->toArray();
    }

    private static function combineDatabaseAggregates($rows, array $plotCounty)
    {
        $countyByPrefix = collect($plotCounty)
            ->filter(fn ($info) => ! empty($info['county']))
            ->mapWithKeys(fn ($info, $plot) => [substr((string) $plot, 0, 3) => $info['county']])
            ->all();

        return $rows->reduce(function ($groups, $row) use ($plotCounty, $countyByPrefix) {
            $plot = (string) $row->plot;
            $county = $plotCounty[$plot]['county']
                ?? $countyByPrefix[substr($plot, 0, 3)]
                ?? $plot;
            $habitatKey = HabitatCode::mapKey($row->habitat_code);
            $key = $county.'|'.$habitatKey;
            $aggregate = $groups->get($key, [
                'county' => $county,
                'habitat_key' => $habitatKey,
                'observation_count' => 0,
                'subplot_count' => 0,
                'coverage_sum' => 0.0,
                'coverage_square_sum' => 0.0,
                'plots' => [],
            ]);
            $aggregate['observation_count'] += (int) $row->observation_count;
            $aggregate['subplot_count'] += (int) $row->subplot_count;
            $aggregate['coverage_sum'] += (float) $row->coverage_sum;
            $aggregate['coverage_square_sum'] += (float) $row->coverage_square_sum;
            $aggregate['plots'][$plot] = true;
            $groups->put($key, $aggregate);

            return $groups;
        }, collect());
    }

    private static function formatAggregate(?array $aggregate): array
    {
        if (! $aggregate || $aggregate['observation_count'] === 0) {
            return [0, 0, 0, 0, []];
        }

        $plots = array_keys($aggregate['plots']);
        sort($plots);
        $mean = $aggregate['coverage_sum'] / $aggregate['observation_count'];
        $meanSquare = $aggregate['coverage_square_sum'] / $aggregate['observation_count'];
        $sd = sqrt(max(0, $meanSquare - ($mean ** 2)));

        return [
            count($plots),
            $aggregate['subplot_count'],
            round($mean, 1),
            round($sd, 1),
            $plots,
        ];
    }

    public static function getCountyFromPlot($plot, $plotCounty)
    {
        // 1. 若有直接對應的 county
        if (! empty($plotCounty[$plot]['county'])) {
            return $plotCounty[$plot]['county'];
        }

        // 2. 否則取前三碼，搜尋其他 plot 的前三碼是否吻合
        $prefix = substr($plot, 0, 3);
        foreach ($plotCounty as $p => $info) {
            if (substr($p, 0, 3) === $prefix && ! empty($info['county'])) {
                return $info['county'];
            }
        }

        // 3. 都找不到，回傳原 plot 當備援
        return $plot;
    }
}
