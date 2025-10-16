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
        $includeCultivated
            ? $base->where(fn($q) => $q->where('s.naturalized','1')->orWhere('s.cultivated','1'))
            : $base->where('s.naturalized','1');

        // 草本/木本
        match ($habMode) {
            'herb'    => $base->whereNotIn('e.habitat_code', ['08','09']),
            'wood'    => $base->whereIn('e.habitat_code',  ['08','09']),
            'wood-08' => $base->where('e.habitat_code','08'),
            'wood-09' => $base->where('e.habitat_code','09'),
            default   => null,
        };

        // 小樣方總數（做平均覆蓋度分母）
        $nSubplots = (clone $base)->distinct('e.plot_full_id')->count('e.plot_full_id');

        // 物種彙總 + 學名組件（不含作者）
        $spAgg = (clone $base)
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
                COUNT(DISTINCT e.plot_full_id)     as freq_cnt
            ')
            ->groupBy('sp','chname','genus','species','ssp','var','subvar','f','cv')
            ->get();

        $totalCov  = (float) $spAgg->sum('cov_sum');
        $totalFreq = (int)   $spAgg->sum('freq_cnt');

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
                    '平均覆蓋度(%)'  => round($avg, 3),
                    '相對覆蓋度(%)'  => round($rc,  3),
                    '相對頻度(%)'    => round($rf,  3),
                    'IVI 重要值(%)'  => round($ivi, 3),
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
