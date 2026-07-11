<?php
namespace App\Helpers;

use App\Models\SubPlotEnv2010;
use App\Models\SubPlotEnv2025;

use App\Helpers\HabHelper;
use App\Support\HabitatCode;

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

        // 2025 新制配對由集中設定補上；2010 衍生規則另見下方 legacyPairs。
        $plotHabList = array_unique(array_merge($plotHab2010, $plotHab2025));
        $plotHabList = HabitatCode::appendDerivedCodes($plotHabList);

        $habTypeOptions = HabHelper::habitatOptions($plotHabList);

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

        // 依 2010 專用配對設定加入衍生地被小樣方。
        $extra = [];
        foreach ($subPlotList2010 as $code) {
            $plotId = substr($code, 0, 6);
            $habType = substr($code, 6, 2);
            $subId = substr($code, 8);

            $understory = HabitatCode::legacyPairs()[$habType] ?? null;
            if ($understory !== null) {
                $extra[] = $plotId . $understory . $subId;
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
