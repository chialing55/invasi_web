<?php

namespace App\Helpers;

use App\Models\SubPlotEnv2010;
use App\Models\SubPlotEnv2025;
use App\Models\SubPlotPlant2010;
use App\Models\SubPlotPlant2025;
use App\Models\SpInfo;
use Illuminate\Support\Facades\DB;
class PlantListHelper
{
    public static function mergeSpeciesLists(array $list2010, array $list2025, $totalPlots2010, $totalPlots2025): array
    {
        $merged = [];

        // 將 2010 清單整理成以 spcode 為 key 的 map
        $map2010 = collect($list2010)->keyBy('spcode');
        $map2025 = collect($list2025)->keyBy('spcode');

        // 所有 spcode 的聯集
        $allSpcodes = $map2010->keys()->merge($map2025->keys())->unique();



        foreach ($allSpcodes as $spcode) {
            $item2010 = $map2010->get($spcode);
            $item2025 = $map2025->get($spcode);

            $chname = $item2010['chname'] ?? $item2025['chname'] ?? $item2010['chname_index'] ?? $item2025['chname_index'] ?? '';
            $chfamily = $item2010['chfamily'] ?? $item2025['chfamily'] ?? '--';

            // 決定 nat_type
            $nat_type = '';
            if (($item2010['naturalized'] ?? $item2025['naturalized'] ?? 0) == 1) {
                $nat_type = '外來';
            } elseif (($item2010['cultivated'] ?? $item2025['cultivated'] ?? 0) == 1) {
                $nat_type = '栽培';
            }

            // 取得 COV 2010 數值
            $covAvg2010 = round($item2010['cov_avg'] ?? 0, 2) ?? null;
            $covSd2010 = round($item2010['cov_sd'] ?? 0, 2) ?? null;
            $covFreq2010 = $item2010['cov_freq'] ?? 0;

            $cov2010Display = $covAvg2010 !== null
                ? round($covAvg2010, 2)
                    . ($covFreq2010 > 1 ? ' ± ' . round($covSd2010 ?? 0, 2) : '')
                    . ($totalPlots2010 > 1 && $covFreq2010 > 0 ? ' (' . $covFreq2010 . ' / ' . $totalPlots2010 . ')' : '')
                : '';

            // 取得 COV 2025 數值
            $covAvg2025 = round($item2025['cov_avg'] ?? 0, 2) ?? null;
            $covSd2025 = round($item2025['cov_sd'] ?? 0, 2) ?? null;
            $covFreq2025 = $item2025['cov_freq'] ?? 0;

            $cov2025Display = $covAvg2025 !== null
                ? round($covAvg2025, 2)
                    . ($covFreq2025 > 1 ? ' ± ' . round($covSd2025 ?? 0, 2) : '')
                    . ($totalPlots2025 > 1 && $covFreq2025 > 0 ? ' (' . $covFreq2025 . ' / ' . $totalPlots2025 . ')' : '')
                : '';

            // 合併後加入陣列
            $merged[] = [
                'chfamily'       => $chfamily,
                'chname'         => $chname,
                'nat_type'       => $nat_type,

                // 顯示用文字（含 ± 與 freq 資訊）
                'covsd2010'      => $cov2010Display,
                'covsd2025'      => $cov2025Display,

                // 數值欄位（用於排序、統計等）
                'cov2010'        => number_format($covAvg2010, 2),
                'sd2010'         => $covFreq2010 > 1 ? "±{$covSd2010}" : "",
                'freq2010'       => ($totalPlots2010 > 1 && $covFreq2010 > 0 ? ' (' . $covFreq2010 . ' / ' . $totalPlots2010 . ')' : ''),
                'plot2010'     => $totalPlots2010,
                'sub2010'      => intval($covFreq2010 ?? 0),

                'cov2025'        => number_format($covAvg2025, 2),
                'sd2025'         => $covFreq2025 > 1 ? "±{$covSd2025}" : "",
                'freq2025'       => ($totalPlots2025 > 1 && $covFreq2025 > 0 ? ' (' . $covFreq2025 . ' / ' . $totalPlots2025 . ')' : ''),
                'plot2025'     => $totalPlots2025,
                'sub2025'      => intval($covFreq2025 ?? 0),

                // 排序欄位（顯示與 cov2010/2025 同值）
                'cov2010_sort'   => $covAvg2010 ?? 0,
                'cov2025_sort'   => $covAvg2025 ?? 0,
            ];

        }

        // 依照 chfamily、chname 排序（中文）
        return collect($merged)->sortBy([['cov2025_sort', 'desc'], ['cov2010_sort', 'desc']])->values()->toArray();
    }
//queryPlot => 取得小樣區/生育地類型的物種清單
public static function getMergedPlotPlantList(string $plot, array $filter = []): array
{

    // dd($plot, $filter);
    // 處理 2010 年資料
    $query2010 = SubPlotPlant2010::select(
        'im_spvptdata_2010.spcode',
        DB::raw('AVG(im_spvptdata_2010.COV) as cov_avg'),
        DB::raw('STD(im_spvptdata_2010.COV) as cov_sd'),
        DB::raw('COUNT(*) as cov_freq')
    )->where('im_spvptdata_2010.PLOT_ID', $plot);

    if (isset($filter['hab_type'])) {
        if (in_array($filter['hab_type'], ['08', '09'])) {
            // 若是 08 或 09 → 原始樣區，SUB_TYPE 必須是 2
            $query2010->where('im_spvptdata_2010.HAB_TYPE', $filter['hab_type'])
                    ->where('im_spvptdata_2010.SUB_TYPE', '2');

        } elseif (in_array($filter['hab_type'], ['88', '99'])) {
            // 88 對應 08、99 對應 09 → 搜原始類型，且 SUB_TYPE != 2
            $originalHab = $filter['hab_type'] === '88' ? '08' : '09';
            $query2010->where('im_spvptdata_2010.HAB_TYPE', $originalHab)
                    ->where('im_spvptdata_2010.SUB_TYPE', '!=', '2');
        } else {
            // 其他類型 → 直接比對 HAB_TYPE
            $query2010->where('im_spvptdata_2010.HAB_TYPE', $filter['hab_type']);
        }
    }


    if (isset($filter['sub_id'])) {
        $query2010->where('im_spvptdata_2010.SUB_ID', $filter['sub_id']);
    }

    $plotPlant2010 = $query2010->groupBy('im_spvptdata_2010.spcode')
        ->get()
        ->keyBy('spcode')
        ->toArray();

    // 取得 spinfo 資料
    $spinfo2010 = Spinfo::whereIn('spcode', array_keys($plotPlant2010))->get()->keyBy('spcode');
    foreach ($plotPlant2010 as $spcode => &$item) {
        $info = $spinfo2010[$spcode] ?? null;
        $item['chname'] = $info['chname'] ?? null;
        $item['chfamily'] = $info['chfamily'] ?? null;
        $item['naturalized'] = $info['naturalized'] ?? null;
        $item['cultivated'] = $info['cultivated'] ?? null;
    }

    // 處理 2025 年資料
    $query2025 = SubPlotPlant2025::select(
        DB::raw('COALESCE(im_spvptdata_2025.spcode, im_spvptdata_2025.chname_index) as spcode'),
        'im_spvptdata_2025.chname_index',
        DB::raw('AVG(im_spvptdata_2025.coverage) as cov_avg'),
        DB::raw('STD(im_spvptdata_2025.coverage) as cov_sd'),
        DB::raw('COUNT(*) as cov_freq')
    );

    if (isset($filter['sub_plot'])) {
        $query2025->where('plot_full_id', $filter['sub_plot']);
    } else if (isset($filter['hab_type'])){
        $filterHab=$plot.$filter['hab_type'];
        $query2025->whereRaw('LEFT(plot_full_id, 8) = ?', [$filterHab]);
    } else {
        $query2025->whereRaw('LEFT(plot_full_id, 6) = ?', [$plot]);
    }

    $plotPlant2025 = $query2025
        ->groupBy('spcode', 'im_spvptdata_2025.chname_index')
        ->get()
        ->keyBy('spcode')
        ->toArray();

    $spinfo2025 = Spinfo::whereIn('spcode', array_keys($plotPlant2025))->get()->keyBy('spcode');

    foreach ($plotPlant2025 as $key => &$item) {
        if (isset($spinfo2025[$key])) {
            $info = $spinfo2025[$key];
            $item['chname'] = $info['chname'] ?? null;
            $item['chfamily'] = $info['chfamily'] ?? null;
            $item['naturalized'] = $info['naturalized'] ?? null;
            $item['cultivated'] = $info['cultivated'] ?? null;
        } else {
            $item['chname'] = $item['chname_index'] ?? null;
            $item['chfamily'] = null;
            $item['naturalized'] = null;
            $item['cultivated'] = null;
        }
    }

    $subQuery = SubPlotEnv2010::selectRaw("DISTINCT CONCAT(PLOT_ID, '.', HAB_TYPE, '.', SUB_ID) AS subkey")
        ->where('PLOT_ID', $plot)
        ->when(isset($filter['hab_type']), function ($query) use ($filter) {
            return $query->where('HAB_TYPE', $filter['hab_type']);
        });


    $totalPlots2010 = DB::connection('invasiflora')
        ->table(DB::raw("({$subQuery->toSql()}) as sub"))
        ->mergeBindings($subQuery->getQuery())
        ->count();


    $totalPlots2025Query = SubPlotEnv2025::whereRaw('LEFT(plot_full_id, 6) = ?', [$plot]);

    if (isset($filter['hab_type'])) {
        $filterHab = $plot . $filter['hab_type']; // e.g. '20800305'
        $totalPlots2025Query->whereRaw('LEFT(plot_full_id, 8) = ?', [$filterHab]);
    }

    $totalPlots2025 = $totalPlots2025Query
        ->distinct('plot_full_id')
        ->count('plot_full_id');

    if (isset($filter['sub_id'])){
        $totalPlots2010 = 1;
        $totalPlots2025 = 1;
    }

    // 合併
    return self::mergeSpeciesLists($plotPlant2010, $plotPlant2025, $totalPlots2010, $totalPlots2025);
}


}
