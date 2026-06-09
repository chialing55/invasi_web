<?php
namespace App\Support;

use App\Models\SubPlotPlant2025;
use Illuminate\Support\Facades\DB;

class FloraGroupStats
{
    /**
     * 類群(蕨/石松/裸子/雙子/單子) × 屬性(特有/原生/歸化/栽培) × 生長習性 的彙整表
     * 不含unknown
     *
     * @param array  $selectedPlots      要統計的 plot 清單
     * @param string $mode               'all' | 'alien-only'
     * @param bool   $includeCultivated  alien-only 模式時，是否把栽培也納入外來
     * @return array ['headings'=>[], 'rows'=>[]]
     */
    public static function taxonLifeformSummaryByQuery(
        array $selectedPlots,
        string $mode = 'all',
        bool $includeCultivated = false
    ): array {
        if (empty($selectedPlots)) return ['headings'=>[], 'rows'=>[]];

        $isNativeExpr = TaiwanChecklistQuery::nativeExpr('s');
        $isEndemicExpr = TaiwanChecklistQuery::endemicExpr('s');
        $isNaturalizedExpr = TaiwanChecklistQuery::naturalizedExpr('s');
        $isCultivatedExpr = TaiwanChecklistQuery::cultivatedExpr('s');

        // 🔹 取「唯一物種清單」作為母集合（避免重複計數）
        $base = SubPlotPlant2025::query()
            ->from((new SubPlotPlant2025)->getTable().' as p')
            ->join('im_splotdata_2025 as e', 'p.plot_full_id', '=', 'e.plot_full_id')
            ->whereIn('e.plot', $selectedPlots);
        TaiwanChecklistQuery::joinCurrent($base, 'p');
        $base->whereNotNull('s.spcode');

        // 僅外來模式：收歸化 +（可選）栽培
        if ($mode === 'alien-only') {
            $base->where(function ($q) use ($includeCultivated, $isNaturalizedExpr, $isCultivatedExpr) {
                $q->whereRaw("({$isNaturalizedExpr}) = 1");
                if ($includeCultivated) $q->orWhereRaw("({$isCultivatedExpr}) = 1");
            });
        }

        // genus（屬名）：從二名法第一個詞切出
        $species = (clone $base)
            ->selectRaw("
                s.plantgroup                           as grp,
                s.family                               as family,
                s.genus                                as genus,
                s.spcode                               as sp,
                {$isNativeExpr}                        as native,
                {$isEndemicExpr}                       as endemic,
                {$isNaturalizedExpr}                   as naturalized,
                {$isCultivatedExpr}                    as cultivated,
                s.growth_form                          as growth_form
            ")
            ->distinct()
            ->get();

        if ($species->isEmpty()) {
            return ['headings'=>[], 'rows'=>[]];
        }

        // 欄位與顯示順序
        $groups = ['石松類植物','蕨類植物','裸子植物','雙子葉植物','單子葉植物'];
        $lifeforms = ['木本','木質藤本','草質藤本','草本'];

        // 建立每群的母集合
        $byGroup = [];
        foreach ($groups as $g) {
            $byGroup[$g] = $species->where('grp', $g);
        }

        // -- 各列指標計算 helper
        $countFamilies = fn($col) => $col->pluck('family')->filter()->unique()->count();
        $countGenera   = fn($col) => $col->pluck('genus')->filter()->unique()->count();
        $countSpecies  = fn($col) => $col->pluck('sp')->filter()->unique()->count();

        $countNative   = fn($col) => $col->where('native', 1)->pluck('sp')->unique()->count();
        $countEndemic  = fn($col) => $col->where('endemic', 1)->pluck('sp')->unique()->count();
        $countAlien    = fn($col) => $col->where('naturalized', 1)->pluck('sp')->unique()->count();
        $countCult     = fn($col) => $col->where('cultivated', 1)->where('naturalized', '!=', 1)->pluck('sp')->unique()->count();

        $countLife = function ($col, $name) {
            return $col->where('growth_form', $name)->pluck('sp')->unique()->count();
        };

        // === 建表 ===
        $headings = array_merge(['隸屬特性'], $groups, ['合計']);
        $rows = [];

        // 類別
        $rows[] = self::buildRow('科數', $byGroup, $countFamilies);
        $rows[] = self::buildRow('屬數', $byGroup, $countGenera);
        $rows[] = self::buildRow('種數', $byGroup, $countSpecies);

        // 屬性
        if ($mode === 'all') {
            $rows[] = self::buildRow('特有種', $byGroup, $countEndemic);
            $rows[] = self::buildRow('原生種', $byGroup, $countNative);
            $rows[] = self::buildRow('歸化種', $byGroup, $countAlien);
            $rows[] = self::buildRow('栽培種', $byGroup, $countCult);
        }

        // 生長習性
        foreach ($lifeforms as $lf) {
            $rows[] = self::buildRow($lf, $byGroup, fn($col) => $countLife($col, $lf));
        }

        // 轉成 StatsTableExport 需要的格式
        $tableRows = [];
        foreach ($rows as [$label, $vals]) {
            $row = ['隸屬特性' => $label];
            $sum = 0;

            foreach ($groups as $g) {
                $v = (array_key_exists($g, $vals) && $vals[$g] !== null) ? (int)$vals[$g] : 0;
                $row[$g] = $v;
                $sum += $v;
            }

            $row['合計'] = $sum;

            // 用 null 先鋪滿，再用 $row 覆蓋，避免欄位遺漏
            $row = array_replace(array_fill_keys($headings, null), $row);

            $tableRows[] = $row;

            $groupOf = function (string $label) use ($lifeforms) {
                if (in_array($label, ['科數','屬數','種數'], true)) return '類別';
                if (in_array($label, ['特有種','原生種','歸化種','栽培種'], true)) return '屬性';
                return '生長習性'; // 其餘當生長習性
            };

            $tableRows = array_map(function ($r) use ($groupOf) {
                $r = ['分組' => $groupOf($r['隸屬特性'] ?? '')] + $r; // 分組放到最左
                return $r;
            }, $tableRows);
            
        }

        // headings 最左邊插入「分組」
        $headings = array_merge(['分組'], $headings);  

        return ['headings' => $headings, 'rows' => $tableRows];
    }

    private static function buildRow(string $label, array $byGroup, callable $fn): array
    {
        $vals = [];
        foreach ($byGroup as $g => $col) {
            $vals[$g] = (int) $fn($col);
        }
        return [$label, $vals];
    }
}
