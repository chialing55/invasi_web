<?php
namespace App\Helpers;

use App\Models\SubPlotEnv2010;
use App\Models\SubPlotEnv2025;
use App\Models\PlotHab;
use App\Helpers\HabHelper;
use Illuminate\Support\Facades\DB;
class PlotCompletedHelper
{
    public static function plotCompleted(string $plot): array
    {
        // 取得生育地代碼
        $plotHab = PlotHab::where('plot', $plot)
            ->pluck('habitat_code')
            ->unique()
            ->values()
            ->toArray();

        $plotHab2025 = SubPlotEnv2025::where('plot', $plot)
            ->pluck('habitat_code')
            ->unique()
            ->values()
            ->toArray();

        // 2025 實際資料：每個 habitat_code 的小樣區數量
        // $habCounts2025 = SubPlotEnv2025::where('plot', $plot)
        //     ->select('habitat_code', DB::raw('count(*) as count_2025'))
        //     ->groupBy('habitat_code')
        //     ->get()
        //     ->map(function ($row) use ($plotHab) {
        //         return [
        //             'habitat_code' => $row->habitat_code,
        //             'count_2025' => $row->count_2025,
        //             'is_recommended' => in_array($row->habitat_code, $plotHab),
        //         ];
        //     })
        //     ->toArray();
            
        // 合併
        $plotHabList = array_unique(array_merge($plotHab, $plotHab2025));

        $habTypeOptions = HabHelper::habitatOptions($plotHabList);

        // 計算交集
        $intersect = array_intersect($plotHab2025, $plotHab);

        // 給予建議訊息
        if (empty($intersect)) {
            $assistant = '❌ 無符合建議類型';
            $completed = false;
        } elseif (count($intersect) === count($plotHab2025)) {
            $assistant = '✅ 類型與建議完全符合';
            $completed = true;
        } else {
            $diff = array_diff($plotHab2025, $plotHab);
            $assistant = '⚠️ 有不在建議清單中的類型：' . implode(', ', $diff);
            $completed = false;
        }


        // 小樣方清單（2025）
        $subPlotList2025 = SubPlotEnv2025::where('plot', $plot)
            ->pluck('plot_full_id')
            ->unique()
            ->values()
            ->toArray();


        return [
            'habTypeOptions' => $habTypeOptions,
            // 'habCounts2025' => $habCounts2025,
            'subPlotList' => $subPlotList2025,
            'assistant' => $assistant,
            'completed' => $completed,
        ];
    }
}
