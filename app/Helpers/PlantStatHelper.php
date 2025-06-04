<?php
namespace App\Helpers;

use App\Models\SubPlotPlant2010;
use App\Models\SubPlotPlant2025;
use App\Models\PlotList2025;
use App\Models\HabitatInfo;

class PlantStatHelper
{
    public static function summarizeByCountyAndHabitat(string $spcode): array
    {
        $plotCounty = PlotList2025::get()->keyBy('plot')->toArray(); // plot => 縣市
        $habTypeMap = HabitatInfo::pluck('habitat', 'habitat_code')->toArray(); // code => 中文名

        // 🟩 讀取並格式化 2010 資料
        $data2010 = SubPlotPlant2010::where('spcode', $spcode)->get()->map(function ($item) {
            return [
                'plot' => $item->PLOT_ID,
                'hab' => $item->HAB_TYPE,
                'sub' => $item->SUB_ID,
                'cov' => $item->COV,
            ];
        });

        // 🟦 讀取並格式化 2025 資料（解碼 plot_full_id）
        $data2025 = SubPlotPlant2025::where('spcode', $spcode)->get()->map(function ($item) {
            $plot_full_id = $item->plot_full_id;
            return [
                'plot' => substr($plot_full_id, 0, 6),
                'hab' => substr($plot_full_id, 6, 2),
                'sub' => substr($plot_full_id, 8, 2),
                'cov' => $item->coverage,
            ];
        });

        // groupBy: [縣市][hab_type]
        // $group2010 = $data2010->groupBy(function ($item) use ($plotCounty) {
        //     return $plotCounty[$item['plot']]['county'] ?? $item['plot'];
        // })->map(function ($itemsByCounty) {
        //     return $itemsByCounty->groupBy('hab');
        // });

        // $group2025 = $data2025->groupBy(function ($item) use ($plotCounty) {
        //     return $plotCounty[$item['plot']]['county'] ?? $item['plot'];
        // })->map(function ($itemsByCounty) {
        //     return $itemsByCounty->groupBy('hab');
        // });

        $group2010 = $data2010->groupBy(function ($item) use ($plotCounty) {
            return self::getCountyFromPlot($item['plot'], $plotCounty);
        })->map(function ($itemsByCounty) {
            return $itemsByCounty->groupBy('hab');
        });

        $group2025 = $data2025->groupBy(function ($item) use ($plotCounty) {
            return self::getCountyFromPlot($item['plot'], $plotCounty);
        })->map(function ($itemsByCounty) {
            return $itemsByCounty->groupBy('hab');
        });


        // 合併所有 (縣市, hab) 組合
        $allKeys = collect();
        foreach ($group2010 as $county => $habGroup) {
            foreach ($habGroup as $hab => $set) {
                $allKeys->push([$county, $hab]);
            }
        }
        foreach ($group2025 as $county => $habGroup) {
            foreach ($habGroup as $hab => $set) {
                $allKeys->push([$county, $hab]);
            }
        }

        $merged = $allKeys->unique(function ($pair) {
            return implode('|', $pair);
        })->map(function ($pair) use ($group2010, $group2025, $habTypeMap) {
            [$county, $habType] = $pair;

            $data2010 = $group2010[$county][$habType] ?? collect();
            $data2025 = $group2025[$county][$habType] ?? collect();

            $stat = function ($items) {
                if ($items->isEmpty()) return [0, 0, 0, 0];
                $plotCount = collect($items)->pluck('plot')->unique()->count();
                $subCount = collect($items)->map(fn($i) => $i['plot'].'.'.$i['hab'].'.'.$i['sub'])->unique()->count();
                $mean = collect($items)->avg('cov');
                $meanSq = collect($items)->avg(fn($i) => $i['cov'] ** 2);
                $sd = sqrt($meanSq - ($mean ** 2));
                return [$plotCount, $subCount, round($mean, 1), round($sd, 1)];
            };

            [$p10, $s10, $m10, $sd10] = $stat($data2010);
            [$p25, $s25, $m25, $sd25] = $stat($data2025);

            return [
                'county' => $county,
                'habitat' => $habTypeMap[$habType] ?? $habType,
                'plot_2010' => $p10,
                'sub_2010' => $s10,
                'cov_sd_2010' => $s10 > 1 ? "{$m10}±{$sd10}" : "{$m10}",
                'cov_2010' => $m10,
                'plot_2025' => $p25,
                'sub_2025' => $s25,
                'cov_sd_2025' => $s25 > 1 ? "{$m25}±{$sd25}" : "{$m25}",
                'cov_2025' => $m25,
            ];
        })->sortBy(['county', 'habitat'])->values()->toArray();

        return $merged;
    }

    public static function getCountyFromPlot($plot, $plotCounty)
    {
        // 1. 若有直接對應的 county
        if (!empty($plotCounty[$plot]['county'])) {
            return $plotCounty[$plot]['county'];
        }

        // 2. 否則取前三碼，搜尋其他 plot 的前三碼是否吻合
        $prefix = substr($plot, 0, 3);
        foreach ($plotCounty as $p => $info) {
            if (substr($p, 0, 3) === $prefix && !empty($info['county'])) {
                return $info['county'];
            }
        }

        // 3. 都找不到，回傳原 plot 當備援
        return $plot;
    }

}
