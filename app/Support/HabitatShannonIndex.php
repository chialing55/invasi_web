<?php
// app/Support/HabitatShannonIndex.php
namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use App\Models\HabitatInfo;

class HabitatShannonIndex
{
    /**
     * 以 selectedPlots 為篩選，直接在 DB 做彙總，再計算每個 habitat 的 Shannon 指數。
     *
     * @param array       $selectedPlots  例：['A01','A02',...]
     * @param bool        $weightByArea   true=用「面積×覆蓋率」作 abundance；false=用覆蓋率加總
     * @param string      $logBase        'e' | '2' | '10'
     * @param string|null $areaField      面積欄位名（在 im_splotdata_2025 上），例：'subplot_area_m2'；沒有就傳 null
     * @return array      每列：['生育地類型','原生種數','歸化種數','歸化種數比例(%)','歸化物種平均覆蓋度(%)','Shannon_歸化','Shannon_原生','Shannon_全部']
     */
    public static function buildHabitatShannonIndexByQuery(
        array $selectedPlots,
        bool $weightByArea = false,
        string $logBase = 'e',
        ?string $areaField = null
    ): array {
        if (empty($selectedPlots)) return [];

        // habitat_code => habitat 名稱
        $habMap = HabitatInfo::pluck('habitat', 'habitat_code')->toArray();

        // 主要彙總：算到 (habitat, status, spcode) 的 x_i
        // x_i = Σ coverage（或 Σ area*coverage/100）
        $areaExpr = $areaField ? "COALESCE(e.`{$areaField}`,1)" : "1";
        $xiExpr   = $weightByArea
            ? "SUM({$areaExpr} * (p.coverage/100.0))"   // 面積加權
            : "SUM(p.coverage)";                        // 覆蓋率加總（百分比尺度）

        $habExpr = "CASE
            WHEN e.habitat_code IN ('88', 88) THEN '08'
            WHEN e.habitat_code IN ('99', 99) THEN '09'
            ELSE LPAD(CAST(e.habitat_code AS CHAR), 2, '0')
        END";    

        $rows = DB::connection('invasiflora')->table('im_spvptdata_2025 as p')
            ->join('im_splotdata_2025 as e', 'p.plot_full_id', '=', 'e.plot_full_id')
            ->leftJoin('spinfo as s', 'p.spcode', '=', 's.spcode')
            ->whereIn('e.plot', $selectedPlots)
            ->selectRaw('
                '.$habExpr.'                    as hab,
                CASE 
                  WHEN s.naturalized != "1" AND s.cultivated != "1" 
                       AND (s.uncertain IS NULL OR s.uncertain != "1")
                  THEN "native" ELSE "naturalized"
                END                           as status,
                p.spcode                      as sp,
                '.$xiExpr.'                   as xi,
                COUNT(*)                      as n_rows,
                SUM(p.coverage)               as sum_cov_rows
            ')
            ->groupBy('hab','status','sp')
            ->get();

        if ($rows->isEmpty()) return [];

        // 對數底
        $logFn = match ($logBase) {
            '2'  => fn($x) => log($x, 2),
            '10' => fn($x) => log($x, 10),
            default => fn($x) => log($x),
        };

        // 轉成 habitat 為主的集合
        $byHab = $rows->groupBy('hab');

        $out = [];
        foreach ($byHab as $hab => $gHab) {
            // 依 status 拆
            $gNative = $gHab->where('status', 'native');
            $gAlien  = $gHab->where('status', 'naturalized');

            // 物種數
            $nNative = $gNative->pluck('sp')->unique()->count();
            $nAlien  = $gAlien->pluck('sp')->unique()->count();
            $nAll    = $nNative + $nAlien;

            // 供 Shannon 用的 xi（以 species 聚合後的 abundance）
            $xiAll    = $gHab   ->groupBy('sp')->map(fn($gg) => (float)$gg->sum('xi'));
            $xiNative = $gNative->groupBy('sp')->map(fn($gg) => (float)$gg->sum('xi'));
            $xiAlien  = $gAlien ->groupBy('sp')->map(fn($gg) => (float)$gg->sum('xi'));

            $H = function (Collection $xi) use ($logFn): float {
                $sumX = (float)$xi->sum();
                if ($sumX <= 0) return 0.0;
                $h = 0.0;
                foreach ($xi as $x) {
                    if ($x <= 0) continue;
                    $p = $x / $sumX;
                    $h -= $p * $logFn($p);
                }
                return round($h, 4);
            };

            // 歸化物種平均覆蓋度(%)：用「每筆記錄」平均（與你原本相近）
            // 注意：若 weightByArea=true，這欄仍是未加權的「記錄平均覆蓋率」
            $sumCovAlienRows = (float) $gAlien->sum('sum_cov_rows');
            $nAlienRows      = (int)   $gAlien->sum('n_rows');
            $avgAlienCover   = $nAlienRows > 0 ? round($sumCovAlienRows / $nAlienRows, 2) : 0.0;

            $out[] = [
                '生育地代碼'           => $hab,
                '生育地類型'           => $habMap[$hab] ?? $hab,
                '原生種數'             => $nNative,
                '歸化種數'             => $nAlien,
                '歸化種數比例(%)'       => $nAll ? round($nAlien / $nAll * 100, 2) : 0.0,
                '歸化物種平均覆蓋度(%)' => $avgAlienCover,
                'Shannon_歸化'         => $H($xiAlien),
                'Shannon_原生'         => $H($xiNative),
                'Shannon_全部'         => $H($xiAll),
            ];
        }

        // 照生育地名稱自然排序
        usort($out, fn($a,$b) => strnatcmp($a['生育地代碼'], $b['生育地代碼']));
        return $out;
    }
}
