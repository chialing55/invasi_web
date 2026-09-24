<?php

namespace App\Support;

use App\Models\HabitatInfo;
use Illuminate\Support\Facades\DB;

class HabitatIVIndex
{
    /**
     * 各生育地「歸化物種重要值」Top N（IV = RC + RF）
     * 僅納入成功對應目前名錄，且來源屬性已分類的物種
     * - RC: 100 * cov_i / sum_cov_hab
     * - RF: 100 * freq_i / n_subplots_hab
     *
     * @param  array  $selectedPlots  要納入的 plot 清單
     * @param  int  $topN  每個生育地取前 N 名
     * @param  string  $labelField  'chname' | 'latinname'（顯示物種名）
     * @param  bool  $includeCultivated  若要把 cultivated 也當外來種，設 true
     * @return array ['headings'=>[], 'rows'=>[]] 可直接丟到表格
     */
    public static function alienImportanceTopNByQuery(
        array $selectedPlots,
        int $topN = 10,
        string $labelField = 'chname',
        bool $includeCultivated = false
    ): array {
        if (empty($selectedPlots)) {
            return ['headings' => [], 'rows' => []];
        }

        // 生育地代碼 → 名稱
        $habMap = HabitatInfo::pluck('habitat', 'habitat_code')->toArray();

        // 共同基礎（全部物種；只過濾樣區）
        $base = DB::connection('invasiflora')->table('im_spvptdata_2025 as p')
            ->join('im_splotdata_2025 as e', 'p.plot_full_id', '=', 'e.plot_full_id')
            ->whereNull('p.deleted_at')
            ->whereNull('e.deleted_at')
            ->whereIn('e.plot', $selectedPlots);
        TaiwanChecklistQuery::joinCurrent($base, 'p');
        TaiwanChecklistQuery::whereClassified($base);

        // 成對森林同時輸出木本、地被與兩者合併的統計。
        $rawHabExpr = "LPAD(CAST(e.habitat_code AS CHAR), 2, '0')";
        $combinedHabExpr = "CONCAT('combined-', ".HabitatCode::normalizedSql('e.habitat_code').')';
        $pairedCodes = array_merge(HabitatCode::woodCodes(), HabitatCode::understoryCodes());
        $habitatGroups = [
            ['expr' => $rawHabExpr, 'base' => clone $base],
            ['expr' => $combinedHabExpr, 'base' => (clone $base)->whereIn('e.habitat_code', $pairedCodes)],
        ];

        // 2) 分子集合：在 baseAll 基礎上加上外來條件（歸化／＋栽培）
        $naturalizedExpr = TaiwanChecklistQuery::naturalizedExpr('s');
        $cultivatedExpr = TaiwanChecklistQuery::cultivatedExpr('s');
        $spAgg = collect();
        $sumCovByHab = collect();
        $sumFreqByHab = collect();

        foreach ($habitatGroups as $group) {
            $habExpr = $group['expr'];
            $groupBase = $group['base'];
            $baseForeign = (clone $groupBase)->where(function ($q) use ($includeCultivated, $naturalizedExpr, $cultivatedExpr) {
                $q->whereRaw("({$naturalizedExpr}) = 1");
                if ($includeCultivated) {
                    $q->orWhereRaw("({$cultivatedExpr}) = 1");
                }
            });

            // 物種層級：每個 (habitat, sp) 的覆蓋度總和 + 出現的子樣區數
            $spAgg = $spAgg->concat((clone $baseForeign)
                ->selectRaw('
                '.$habExpr.'    as hab,
                s.spcode          as sp,
                s.chname          as chname,
                s.full_name       as latinname,
                SUM(p.coverage)   as cov_sum,
                COUNT(DISTINCT p.plot_full_id) as freq_cnt
            ')
                ->groupBy('hab', 's.spcode', 's.chname', 's.full_name')
                ->get());

            $sumCovByHab = $sumCovByHab->merge((clone $groupBase)
                ->selectRaw("{$habExpr} as hab, SUM(p.coverage) as cov_sum_hab")
                ->groupBy('hab')
                ->pluck('cov_sum_hab', 'hab'));

            $speciesKeyExpr = 's.spcode';
            $sumFreqByHab = $sumFreqByHab->merge((clone $groupBase)
                ->selectRaw("{$habExpr} as hab, {$speciesKeyExpr} as sp, COUNT(DISTINCT p.plot_full_id) as n")
                ->groupByRaw("{$habExpr}, {$speciesKeyExpr}")
                ->get()
                ->groupBy('hab')
                ->map(fn ($g) => (int) $g->sum('n')));
        }

        if ($spAgg->isEmpty()) {
            return ['headings' => [], 'rows' => []];
        }

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
            $denCov = max(0.000001, (float) ($sumCovByHab[$hab] ?? 0));
            $denFreq = max(1, (int) ($sumFreqByHab[$hab] ?? 0));

            $list = $rows->map(function ($r) use ($denCov, $denFreq, $labelField) {
                $rc = 100.0 * ((float) $r->cov_sum) / $denCov;
                $rf = 100.0 * ((int) $r->freq_cnt) / $denFreq;
                $iv = $rc + $rf; // 若要平均：($rc + $rf)/2

                return [
                    'label' => $labelField === 'latinname' ? $r->latinname : $r->chname,
                    'iv' => round($iv, 2),
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

        $headings = array_merge(['排名'], array_map(fn ($k) => HabitatCode::analysisLabel($k, $habMap), $habKeys));

        $rows = [];
        for ($rank = 1; $rank <= $topN; $rank++) {
            $row = ['排名' => $rank];
            foreach ($habKeys as $k) {
                $item = $habLists[$k][$rank - 1] ?? null;
                $row[HabitatCode::analysisLabel($k, $habMap)] = $item
                    ? ($item['label']."\n(".$item['iv'].')')
                    : '-';
            }
            $rows[] = $row;
        }

        return ['headings' => $headings, 'rows' => $rows];
    }
}
