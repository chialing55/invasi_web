<?php

namespace App\Exports;

use App\Models\SubPlotPlant2010;
use App\Support\ScientificNameHelper;
use App\Support\TaiwanChecklistQuery;
use PhpOffice\PhpSpreadsheet\RichText\RichText;

class PlantListExport2010
{
    public static function PlantListDistinctForPlots(array $selectedPlots, string $format = 'xlsx'): array
    {
        if (empty($selectedPlots)) {
            return ['headings' => [], 'rows' => []];
        }

        $builder = SubPlotPlant2010::query()
            ->from('im_spvptdata_2010 as p')
            ->whereIn('p.PLOT_ID', $selectedPlots)
            ->whereNotNull('p.spcode');

        TaiwanChecklistQuery::joinCurrent($builder, 'p');
        $builder->whereNotNull('s.spcode')
            ->groupBy('s.spcode')
            ->selectRaw(self::listSelectSql());

        self::applyListOrder($builder);

        $headings = ['科名', '學名', '中文名', '原生種', '特有種', '歸化種', '栽培種', 'IUCN', 'taicol_taxon_id', '簡化學名'];
        $rows = $builder->toBase()->get()
            ->map(fn($row) => self::formatSpeciesRow((array) $row, $headings, $format))
            ->values()
            ->all();

        return ['headings' => $headings, 'rows' => $rows];
    }

    private static function listSelectSql(): string
    {
        return "
            MAX(s.plantgroup) AS pg,
            MAX(s.family) AS family,
            MAX(s.full_name) AS latin,
            MAX(s.full_name) AS full_name,
            MAX(s.canonical_name) AS canonical_name,
            MAX(COALESCE(NULLIF(s.chfamily, ''), s.family)) AS `科名`,
            MAX(s.full_name) AS `學名`,
            MAX(s.canonical_name) AS `簡化學名`,
            MAX(s.chname) AS `中文名`,
            MAX(CASE WHEN " . TaiwanChecklistQuery::nativeExpr('s') . " = 1 THEN '1' ELSE '' END) AS `原生種`,
            MAX(CASE WHEN " . TaiwanChecklistQuery::endemicExpr('s') . " = 1 THEN '1' ELSE '' END) AS `特有種`,
            MAX(CASE WHEN " . TaiwanChecklistQuery::naturalizedExpr('s') . " = 1 THEN '1' ELSE '' END) AS `歸化種`,
            MAX(CASE WHEN " . TaiwanChecklistQuery::cultivatedExpr('s') . " = 1 THEN '1' ELSE '' END) AS `栽培種`,
            MAX(s.taicol_taxon_id) AS `taicol_taxon_id`,
            MAX(s.IUCN) AS `IUCN`
        ";
    }

    private static function applyListOrder($builder): void
    {
        $pgOrder = ['石松類植物', '蕨類植物', '裸子植物', '雙子葉植物', '單子葉植物'];
        $pgPlaceholders = implode(',', array_fill(0, count($pgOrder), '?'));
        $builder
            ->orderByRaw("FIELD(pg, {$pgPlaceholders})", $pgOrder)
            ->orderBy('family')
            ->orderBy('latin');
    }

    private static function formatSpeciesRow(array $arr, array $headings, string $format): array
    {
        if ($format === 'xlsx') {
            $html = ScientificNameHelper::italicize($arr['full_name'] ?? ($arr['學名'] ?? ''), $arr['canonical_name'] ?? '');
            $arr['學名'] = self::emHtmlToRichText($html);
        } else {
            $arr['學名'] = self::decodeText((string) ($arr['學名'] ?? ''));
        }

        $arr['簡化學名'] = self::decodeText((string) ($arr['簡化學名'] ?? ($arr['canonical_name'] ?? '')));

        unset($arr['pg'], $arr['family'], $arr['latin'], $arr['full_name'], $arr['canonical_name']);

        $ordered = [];
        foreach ($headings as $heading) {
            $ordered[$heading] = $arr[$heading] ?? '';
        }

        return $ordered;
    }

    private static function decodeText(string $text): string
    {
        return html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private static function emHtmlToRichText(string $html): RichText
    {
        $rt = new RichText();
        $html = str_replace(["\r", "\n", '&nbsp;'], ' ', $html);
        $html = str_replace(['<em>', '</em>'], ["\x01", "\x02"], $html);
        $parts = preg_split('/(\x01|\x02)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        $italic = false;

        foreach ($parts ?: [] as $part) {
            if ($part === "\x01") {
                $italic = true;
                continue;
            }
            if ($part === "\x02") {
                $italic = false;
                continue;
            }
            if ($part === '') {
                continue;
            }
            $run = $rt->createTextRun(self::decodeText($part));
            $run->getFont()->setItalic($italic);
        }

        return $rt;
    }
}
