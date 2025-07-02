<?php
namespace App\Helpers;

use App\Models\SubPlotEnv2010;
use App\Models\SubPlotEnv2025;
use App\Models\HabitatInfo;

class PlotHelper
{
    public static function getSubPlotInfo(string $plot): array
    {
        // 取得生育地代碼
        $plotHab2010 = SubPlotEnv2010::where('PLOT_ID', $plot)
            ->pluck('HAB_TYPE')
            ->unique()
            ->values()
            ->toArray();

        $plotHab2025 = SubPlotEnv2025::where('plot', $plot)
            ->pluck('habitat_code')
            ->unique()
            ->values()
            ->toArray();

        // 合併並補上 88/99
        $plotHabList = array_unique(array_merge($plotHab2010, $plotHab2025));
        if (in_array('08', $plotHabList)) $plotHabList[] = '88';
        if (in_array('09', $plotHabList)) $plotHabList[] = '99';

        $habTypeMap = HabitatInfo::pluck('habitat', 'habitat_code')->toArray();
        $habTypeOptions = collect($plotHabList)
            ->filter(fn($code) => isset($habTypeMap[$code]))
            ->mapWithKeys(fn($code) => [$code => $habTypeMap[$code]])
            ->sortBy(fn($label, $code) => $label)
            ->toArray();

        // 小樣方清單（2010）
        $subPlotList2010 = SubPlotEnv2010::where('PLOT_ID', $plot)
            ->select('PLOT_ID', 'HAB_TYPE', 'SUB_ID')
            ->get()
            ->map(function ($item) {
                return $item->PLOT_ID . $item->HAB_TYPE . $item->SUB_ID;
            })
            ->unique()
            ->values()
            ->toArray();

        // 加入 88/99 小樣方（衍生）
        $extra = [];
        foreach ($subPlotList2010 as $code) {
            $plotId = substr($code, 0, 6);
            $habType = substr($code, 6, 2);
            $subId = substr($code, 8);

            if ($habType === '08') {
                $extra[] = $plotId . '88' . $subId;
            } elseif ($habType === '09') {
                $extra[] = $plotId . '99' . $subId;
            }
        }

        $subPlotList2010 = collect(array_merge($subPlotList2010, $extra))
            ->unique()
            ->values()
            ->toArray();

        // 小樣方清單（2025）
        $subPlotList2025 = SubPlotEnv2025::where('plot', $plot)
            ->pluck('plot_full_id')
            ->unique()
            ->values()
            ->toArray();

        $subPlotList = array_unique(array_merge($subPlotList2010, $subPlotList2025));
        sort($subPlotList);

        return [
            'habTypeOptions' => $habTypeOptions,
            'subPlotList' => $subPlotList,
        ];
    }
}
