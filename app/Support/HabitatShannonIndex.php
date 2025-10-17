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


        $habExpr = "CASE
            WHEN e.habitat_code IN ('88', 88) THEN '08'
            WHEN e.habitat_code IN ('99', 99) THEN '09'
            ELSE LPAD(CAST(e.habitat_code AS CHAR), 2, '0')
            END";    

        $statusExpr = "CASE
            WHEN COALESCE(s.naturalized, 0) = 1 THEN 'naturalized'
            WHEN COALESCE(s.cultivated , 0) = 1 THEN 'cultivated'
            WHEN COALESCE(s.uncertain  , 0) = 1 THEN 'uncertain'
            ELSE 'native'
            END";

        // 🔹 取「唯一物種清單」作為母集合（避免重複計數）
        $base = DB::connection('invasiflora')->table('im_spvptdata_2025 as p')
            ->join('im_splotdata_2025 as e', 'p.plot_full_id', '=', 'e.plot_full_id')
            ->leftJoin('spinfo as s', 'p.spcode', '=', 's.spcode')
            ->whereIn('e.plot', $selectedPlots);

        $rows = (clone $base)
            ->selectRaw('
                '.$habExpr.'                  as hab,
                '.$statusExpr.'               as status,
                p.spcode                      as sp,
                COUNT(*)                      as n_rows,
                SUM(p.coverage)               as sum_cov_rows
            ')
            ->groupBy('hab','status','sp')
            ->get();

        if ($rows->isEmpty()) return [];

/*
歸化物種平均覆蓋度 Naturalized plant average coverage
Σ(該小樣方之歸化植物總覆蓋度/該小樣方之總覆蓋度× 100 %)/總小樣方數
*/
        /* === 新增：依⑤公式計「各生育地的歸化物種平均覆蓋度(%)」 === */
        /* 先算每個小樣方的「總覆蓋度」與「歸化覆蓋度」，再把(歸化/總*100)做平均 */
        $alienCovExpr = "
        CASE
        WHEN s.naturalized = '1'
        THEN p.coverage ELSE 0
        END";

        $sub = (clone $base)
            ->selectRaw("{$habExpr} as hab, p.plot_full_id as plot_full_id,
                        SUM(p.coverage)                 as total_cov,
                        SUM({$alienCovExpr})            as alien_cov")
            ->groupBy('hab','p.plot_full_id');

        $avgPctByHab = DB::connection('invasiflora')->query()->fromSub($sub, 't')
            ->selectRaw("hab,
                AVG(CASE WHEN total_cov > 0 THEN 100 * alien_cov / total_cov ELSE 0 END) AS avg_pct")
            ->groupBy('hab')
            ->pluck('avg_pct','hab')       // -> ['08'=>xx.x, '09'=>yy.y, ...]
            ->map(fn($v) => round((float)$v, 2))
            ->all(); 
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
            $gCultiv  = $gHab->where('status', 'cultivated');

            // 物種數
            $nNative = $gNative->pluck('sp')->unique()->count();
            $nAlien  = $gAlien->pluck('sp')->unique()->count();
            $nCultiv  = $gCultiv->pluck('sp')->unique()->count();
            $nAll    = $gHab->pluck('sp')->unique()->count();

            // 供 Shannon 用的 xi（以 species 聚合後的 abundance）
            $xiAll    = $gHab   ->pluck('sum_cov_rows', 'sp')->map(fn($v) => (float)$v);
            $xiNative = $gNative->pluck('sum_cov_rows', 'sp')->map(fn($v) => (float)$v);
            $xiAlien  = $gAlien ->pluck('sum_cov_rows', 'sp')->map(fn($v) => (float)$v);

/*
𝑝𝒾=𝑥𝑖Σ𝑥𝑖𝑠𝑖=1 𝐻′=−Σ𝑝𝑖×log𝑝𝑖𝑆𝑖 𝑥=物種覆蓋度。
𝑠=物種數。
𝑝𝑖=物種覆蓋度所佔比例。

Shannon 指數 H' = - Σ (p_i * log_b(p_i))
其中 p_i = x_i / Σx_i
x_i 為第 i 物種的 abundance（本例中為覆蓋度加總或面積加權覆蓋度加總）
b 為對數底（常用 e、2、10）
*/

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
           

            $avgAlienCover = isset($avgPctByHab[$hab]) ? (float)$avgPctByHab[$hab] : 0.0;

            $out[] = [
                '生育地代碼'           => $hab,
                '生育地類型'           => $habMap[$hab] ?? $hab,
                '原生種數'             => $nNative,
                '歸化種數'             => $nAlien,
                '栽培種數'             => $nCultiv,
                '歸化種數比例(%)'       => $nAll ? round($nAlien / $nAll * 100, 2) : 0.0,
                '歸化物種平均覆蓋度(%)' => $avgAlienCover,
                'Shannon_歸化'         => $H($xiAlien),
                'Shannon_原生'         => $H($xiNative),
                'Shannon_全部'         => $H($xiAll),
            ];
        }

        // 照生育地名稱自然排序
        usort($out, fn($a,$b) => strnatcmp($a['生育地代碼'], $b['生育地代碼']));

        array_walk($out, function (&$r) {
            unset($r['生育地代碼']);
        });

        return $out;
    }
}
