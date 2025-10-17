<?php
namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use App\Models\HabitatInfo;
use App\Models\SubPlotEnv2025;
use App\Models\SubPlotPlant2025;

class HabitatIVIndex
{
    /**
     * 各生育地「歸化物種重要值」Top N（IV = RC + RF）
     * - RC: 100 * cov_i / sum_cov_hab
     * - RF: 100 * freq_i / n_subplots_hab
     *
     * @param array  $selectedPlots  要納入的 plot 清單
     * @param int    $topN           每個生育地取前 N 名
     * @param string $labelField     'chname' | 'latinname'（顯示物種名）
     * @param bool   $includeCultivated 若要把 cultivated 也當外來種，設 true
     * @return array ['headings'=>[], 'rows'=>[]] 可直接丟到表格
     */
    public static function alienImportanceTopNByQuery(
        array $selectedPlots,
        int $topN = 10,
        string $labelField = 'chname',
        bool $includeCultivated = false
    ): array {
        if (empty($selectedPlots)) return ['headings'=>[], 'rows'=>[]];

        // 生育地代碼 → 名稱
        $habMap = HabitatInfo::pluck('habitat', 'habitat_code')->toArray();

        // 基礎條件（外來：naturalized=1；可選擇包含 cultivated）
        $base = DB::connection('invasiflora')->table('im_spvptdata_2025 as p')
            ->join('im_splotdata_2025 as e', 'p.plot_full_id', '=', 'e.plot_full_id')
            ->join('spinfo as s', 'p.spcode', '=', 's.spcode')
            ->whereIn('e.plot', $selectedPlots);

        if ($includeCultivated) {
            $base->where(function ($q) {
                $q->where('s.naturalized', '1')->orWhere('s.cultivated', '1');
            });
        } else {
            $base->where('s.naturalized', '1');
        }
        // 統一生育地代碼：88=>08、99=>09，其餘補成兩位
        $habExpr = "CASE
            WHEN e.habitat_code IN ('88', 88) THEN '08'
            WHEN e.habitat_code IN ('99', 99) THEN '09'
            ELSE LPAD(CAST(e.habitat_code AS CHAR), 2, '0')
        END";

        // 物種層級：每個 (habitat, sp) 的覆蓋度總和 + 出現的子樣區數
        $spAgg = (clone $base)
            ->selectRaw('
                '.$habExpr.'    as hab,
                p.spcode          as sp,
                s.chname          as chname,
                s.latinname       as latinname,
                SUM(p.coverage)   as cov_sum,   
                COUNT(DISTINCT p.plot_full_id) as freq_cnt
            ')
            ->groupBy('hab','sp','chname','latinname')
            ->get();

        if ($spAgg->isEmpty()) return ['headings'=>[], 'rows'=>[]];

        // 各生育地：全部歸化種覆蓋度總和、該生育地的子樣區總數
        $sumCovByHab = (clone $base)
            ->selectRaw("{$habExpr} as hab, SUM(p.coverage) as cov_sum_hab")
            ->groupBy('hab')
            ->pluck('cov_sum_hab', 'hab');

        $nSubplotByHab = (clone $base)
            ->selectRaw("{$habExpr} as hab, COUNT(DISTINCT p.plot_full_id) as n_subplots")
            ->groupBy('hab')
            ->pluck('n_subplots', 'hab');

        // 計 IV（RC + RF）
/*
1. 相對頻度（ Relative frequency)=（某一物種的頻度 /所有物種之頻度） × 100 %
若計算範圍為「行政區」，其計算方式如下：
相對頻度=（某物種於該行政區出現的小樣方數 /該行政區所有物種出現的小樣方數總和） × 100%
2. 相對覆蓋度 Relative coverage = （某一物種的覆蓋度 /所有物種之覆蓋度） × 100 %
若計算範圍為「行政區」，其計算方式如下：
相對覆蓋度=（某物種於該行政區之總覆蓋度 /該行政區所有物種的總覆蓋度） × 100%
4. 重要值指數 Importance value index, IVI
相對頻度（%））+ 相對覆蓋度

*/

        $byHab = $spAgg->groupBy('hab');
        $habLists = [];
        foreach ($byHab as $hab => $rows) {
            $denCov = max(0.000001, (float)($sumCovByHab[$hab] ?? 0));
            $denN   = max(1, (int)($nSubplotByHab[$hab] ?? 1));

            $list = $rows->map(function ($r) use ($denCov, $denN, $labelField) {
                $rc = 100.0 * ((float)$r->cov_sum) / $denCov;
                $rf = 100.0 * ((int)$r->freq_cnt) / $denN;
                $iv = $rc + $rf; // 若要平均：($rc + $rf)/2
                return [
                    'label' => $labelField === 'latinname' ? $r->latinname : $r->chname,
                    'iv'    => round($iv, 2),
                ];
            })
            ->sortByDesc('iv')
            ->values()
            ->take($topN)
            ->all();

            $habLists[$hab] = $list;
        }

        // 產生「矩陣」：col = 生育地，row = 排名 1..N；cell = 名稱 \n(IV)
        $habKeys = array_keys($habLists);
        // 以名稱排序
        // usort($habKeys, fn($a,$b)=>strnatcmp($habMap[$a] ?? $a, $habMap[$b] ?? $b));

        $headings = array_merge(['排名'], array_map(fn($k)=>$habMap[$k] ?? $k, $habKeys));

        $rows = [];
        for ($rank=1; $rank <= $topN; $rank++) {
            $row = ['排名' => $rank];
            foreach ($habKeys as $k) {
                $item = $habLists[$k][$rank-1] ?? null;
                $row[$habMap[$k] ?? $k] = $item
                    ? ($item['label'] . "\n(" . $item['iv'] . ")")
                    : '-';
            }
            $rows[] = $row;
        }

        return ['headings'=>$headings, 'rows'=>$rows];
    }
}
