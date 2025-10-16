<?php
declare(strict_types=1);

namespace App\Support;

final class SpNameHelper
{
    /**
     * 將物種各欄位組成帶有 <em> 標籤的學名字串、簡化搜尋字串與顯示用標題。
     *
     * @param  array<string, string|null> $a
     *  期望鍵值（若沒有則給空字串）：
     *  genus, species, autsp1, autsp2, ssp, autssp1, autssp2, var, autvar1, autvar2,
     *  subvar, autsubvar1, autsubvar2, f, autf1, autf2, cv, autcv1, autcv2,
     *  auctnon, type, code
     *
     * @return array{name:string, ebooksearch:string, simnametitle:string, type:string, code:string}
     */
    public static function combine(array $a): array
    {
        // 安全取值：沒有就回空字串，避免未定義索引
        $val = static function (string $key) use ($a): string {
            $v = $a[$key] ?? '';
            return is_string($v) ? trim($v) : '';
        };

        $genus   = $val('genus');
        $species = $val('species');

        // 基本學名
        $name          = "<em>{$genus} {$species}</em>";
        $ebooksearch   = "{$genus} {$species}";
        $simnametitle  = "<em>{$genus} {$species}</em>";

        // 判斷是否需要 auct. non（非 "0" 且非空視為需要）
        $needAuctNon = $val('auctnon') !== '' && $val('auctnon') !== '0';

        // 找出「最深層階級」：cv > f > subvar > var > ssp > sp
        $deepestRank = 'sp';
        foreach (['cv', 'f', 'subvar', 'var', 'ssp'] as $rk) {
            if ($val($rk) !== '') {
                $deepestRank = $rk;
            }
        }

        // ===== 物種層（sp）作者 =====
        if ($val('autsp1') !== '') {
            $name .= ' ' . $val('autsp1') . ' <em>ex</em>';
        }
        // auct. non 只在「最深層階級」的作者前面插入；如果最深層是 sp，就在 autsp2 前面
        $name .= ($needAuctNon && $deepestRank === 'sp') ? ' <em>auct. non</em>' : '';
        $name .= ' ' . $val('autsp2');

        // ===== 亞種（ssp）=====
        if ($val('ssp') !== '') {
            $name         .= " ssp. <em>{$val('ssp')}</em>";
            $simnametitle .= " ssp. <em>{$val('ssp')}</em>";

            if ($val('autssp1') !== '') {
                $name .= ' ' . $val('autssp1') . ' <em>ex</em>';
            }
            $name .= ($needAuctNon && $deepestRank === 'ssp') ? ' <em>auct. non</em>' : '';
            $name .= ' ' . $val('autssp2');
        }

        // ===== 變種（var）=====
        if ($val('var') !== '') {
            $name         .= " var. <em>{$val('var')}</em>";
            $simnametitle .= " var. <em>{$val('var')}</em>";
            $ebooksearch  .= ' ' . $val('var');

            if ($val('autvar1') !== '') {
                $name .= ' ' . $val('autvar1') . ' <em>ex</em>';
            }
            $name .= ($needAuctNon && $deepestRank === 'var') ? ' <em>auct. non</em>' : '';
            $name .= ' ' . $val('autvar2');
        }

        // ===== 亞變種（subvar）=====
        if ($val('subvar') !== '') {
            $name         .= " subvar. <em>{$val('subvar')}</em>";
            $simnametitle .= " subvar. <em>{$val('subvar')}</em>";
            $ebooksearch  .= ' ' . $val('subvar');

            if ($val('autsubvar1') !== '') {
                $name .= ' ' . $val('autsubvar1') . ' <em>ex</em>';
            }
            $name .= ($needAuctNon && $deepestRank === 'subvar') ? ' <em>auct. non</em>' : '';
            $name .= ' ' . $val('autsubvar2');
        }

        // ===== 變型（f.）=====
        if ($val('f') !== '') {
            $name         .= " f. <em>{$val('f')}</em>";
            $simnametitle .= " f. <em>{$val('f')}</em>";
            $ebooksearch  .= ' ' . $val('f');

            if ($val('autf1') !== '') {
                $name .= ' ' . $val('autf1') . ' <em>ex</em>';
            }
            $name .= ($needAuctNon && $deepestRank === 'f') ? ' <em>auct. non</em>' : '';
            $name .= ' ' . $val('autf2');
        }

        // ===== 園藝品種（cv.）=====
        if ($val('cv') !== '') {
            $name         .= " cv. <em>{$val('cv')}</em>";
            $simnametitle .= " cv. <em>{$val('cv')}</em>";
            $ebooksearch  .= ' ' . $val('cv');

            if ($val('autcv1') !== '') {
                $name .= ' ' . $val('autcv1') . ' <em>ex</em>';
            }
            $name .= ($needAuctNon && $deepestRank === 'cv') ? ' <em>auct. non</em>' : '';
            $name .= ' ' . $val('autcv2');
        }

        return [
            'name'         => trim(preg_replace('/\s+/', ' ', $name)),
            'ebooksearch'  => trim(preg_replace('/\s+/', ' ', $ebooksearch)),
            'simnametitle' => trim(preg_replace('/\s+/', ' ', $simnametitle)),
            'spcode'         => $val('spcode'),
        ];
    }
}
