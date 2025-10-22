<?php
namespace App\Support;

use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use App\Support\SpNameHelper;

final class FloraIVISupport
{
    // 把 <em> 轉 RichText：只把 em 的內容設為 italic
    private static function emHtmlToRichText(string $html): RichText
    {
        $rt = new RichText();
        $html = str_replace(["\r","\n",'&nbsp;'], ' ', $html);
        $html = str_replace(['<em>','</em>'], ["\x01","\x02"], $html);

        $parts = preg_split('/(\x01|\x02)/', $html, -1,
            PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        $italic = false;
        foreach ($parts as $p) {
            if ($p === "\x01") { $italic = true;  continue; }
            if ($p === "\x02") { $italic = false; continue; }
            if ($p === '') continue;
            $run = $rt->createTextRun($p);
            $run->getFont()->setItalic($italic);
        }
        return $rt;
    }

    /**
     * 依條件產生「歸化物種 IVI 表」：學名為簡化版（無作者），僅拉丁詞斜體
     */
    public static function iviTable(
        array $selectedPlots,
        string $habMode = 'herb',      // herb | wood | wood-08 | wood-09 | all
        bool $includeCultivated = false,
    ): array {
        $db = DB::connection('invasiflora');

        // 基礎
        $base = $db->table('im_spvptdata_2025 as p')
            ->join('im_splotdata_2025 as e', 'p.plot_full_id', '=', 'e.plot_full_id')
            ->join('spinfo as s', 'p.spcode', '=', 's.spcode')
            ->whereIn('e.plot', $selectedPlots);

        // 外來條件
        // $base->where(function ($q) use ($includeCultivated) {
        //     $q->where('s.naturalized', '1');
        //     if ($includeCultivated) {
        //         $q->orWhere('s.cultivated', '1');
        //     }
        // });

        // 草本/木本
        match ($habMode) {
            'herb'    => $base->whereNotIn('e.habitat_code', ['08','09']),
            'wood'    => $base->whereIn('e.habitat_code',  ['08','09']),
            'wood-08' => $base->where('e.habitat_code','08'),
            'wood-09' => $base->where('e.habitat_code','09'),
            default   => null,
        };
        // 2) 分子集合：在 baseAll 基礎上加上外來條件（歸化／＋栽培）
        $baseForeign = (clone $base)->where(function ($q) use ($includeCultivated) {
            $q->where('s.naturalized', '1');
            if ($includeCultivated) {
                $q->orWhere('s.cultivated', '1');
            }
        });

        // 物種彙總 + 學名組件（不含作者）（分子）
        $spAgg = (clone $baseForeign)
            ->selectRaw('
                p.spcode                         as sp,
                s.chname                         as chname,
                s.genus                          as genus,
                s.species                        as species,
                s.ssp                            as ssp,
                s.var                            as var,
                s.subvar                         as subvar,
                s.f                              as f,
                s.cv                             as cv,
                SUM(p.coverage)                  as cov_sum,
                COUNT(DISTINCT p.plot_full_id)     as freq_cnt
            ')
            ->groupBy(
                'p.spcode','s.chname','s.genus','s.species','s.ssp','s.var','s.subvar','s.f','s.cv')
            ->get();

        // 4) 分母：用「全部物種」計算
        // 4a) 小樣方總數（平均覆蓋度分母）：是樣區選擇下的所有小樣方數
        $nSubplots = (clone $base)->distinct('p.plot_full_id')->count('p.plot_full_id');

        // 4b) 所有物種之「總覆蓋度」分母
        $totalCov = (float) (clone $base)->sum('p.coverage');

        // 4c) 所有物種之「頻度總和」分母（= 各物種在不同小樣方出現數的加總）
        //     不能用單純 DISTINCT 小樣方數，要「先依物種算 distinct，再把各物種相加」
        $totalFreq = (int) (clone $base)
            ->select('p.spcode')
            ->selectRaw('COUNT(DISTINCT p.plot_full_id) as n')
            ->groupBy('p.spcode')
            ->get()
            ->sum('n');

        // 5) 分子總和（如果你需要）
        $totalCovForeign  = (float) $spAgg->sum('cov_sum');
        $totalFreqForeign = (int)   $spAgg->sum('freq_cnt');            
/*
1. 相對頻度（ Relative frequency)=（某一物種的頻度 /所有物種之頻度） × 100 %
若計算範圍為「行政區」，其計算方式如下：
相對頻度=（某物種於該行政區出現的小樣方數 /該行政區所有物種出現的小樣方數總和） × 100%
2. 相對覆蓋度 Relative coverage = （某一物種的覆蓋度 /所有物種之覆蓋度） × 100 %
若計算範圍為「行政區」，其計算方式如下：
相對覆蓋度=（某物種於該行政區之總覆蓋度 /該行政區所有物種的總覆蓋度） × 100%
3. 平均覆蓋度（ Average coverage
某物種於該行政區之總覆蓋度/該行政區之總小樣方數
4. 重要值指數 Importance value index, IVI
相對頻度（%））+ 相對覆蓋度

*/

        $rows = $spAgg->map(function ($r) use ($nSubplots, $totalCov, $totalFreq) {
                // 用你的 helper 組「簡化學名」（不含作者）
                $sim = SpNameHelper::combine([
                    'genus' => $r->genus, 'species' => $r->species,
                    'ssp'   => $r->ssp,   'var'     => $r->var,
                    'subvar'=> $r->subvar,'f'      => $r->f,
                    'cv'    => $r->cv,
                    // 其他鍵給空字串即可（helper 會處理）
                ])['simnametitle']; // 含 <em> 的簡化學名

                $avg = $nSubplots > 0 ? $r->cov_sum / $nSubplots : 0.0;       // 平均覆蓋度(%)
                $rc  = $totalCov  > 0 ? $r->cov_sum / $totalCov * 100 : 0.0;  // 相對覆蓋度(%)
                $rf  = $totalFreq > 0 ? $r->freq_cnt / $totalFreq * 100 : 0.0;// 相對頻度(%)
                $ivi = $rc + $rf;

                return [
                    '中文名'        => $r->chname,
                    // 這格給 RichText（Excel 只會把 <em> 部分斜體）
                    '學名'          => self::emHtmlToRichText($sim),
                    '平均覆蓋度(%)'  => $avg,
                    '相對覆蓋度(%)'  => $rc,
                    '相對頻度(%)'    => $rf,
                    'IVI 重要值(%)'  => $ivi,
                ];
            })
            ->sortByDesc('IVI 重要值(%)')
            ->values()
            ->all();

        return [
            'headings' => ['中文名','學名','平均覆蓋度(%)','相對覆蓋度(%)','相對頻度(%)','IVI 重要值(%)'],
            'rows'     => $rows,
        ];
    }
}
