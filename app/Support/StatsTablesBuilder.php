<?php

namespace App\Support;

class StatsTablesBuilder
{
    public static function sectionKeys(): array
    {
        return [
            'all_traits',
            'alien_traits',
            'habitat_shannon',
            'habitat_iv',
            'herb_ivi',
            'wood_ivi',
            'ivi_comparison',
            'top_families',
            'family_comparison',
        ];
    }

    public static function tableKeys(): array
    {
        return [
            'all_traits',
            'alien_traits',
            'habitat_shannon',
            'habitat_iv',
            'herb_ivi',
            'wood_ivi',
            'ivi_comparison',
        ];
    }

    public static function figureKeys(): array
    {
        return [
            'top_families',
            'family_comparison',
        ];
    }

    public static function build(array $selectedPlots): array
    {
        $sections = [];
        foreach (self::sectionKeys() as $key) {
            $section = self::buildOne($key, $selectedPlots);
            if ($section !== null) {
                $sections[] = $section;
            }
        }

        return $sections;
    }

    public static function buildOne(string $key, array $selectedPlots): ?array
    {
        return match ($key) {
            'all_traits' => self::allTraits($selectedPlots),
            'alien_traits' => self::alienTraits($selectedPlots),
            'habitat_shannon' => self::habitatShannon($selectedPlots),
            'habitat_iv' => self::habitatIv($selectedPlots),
            'top_families' => self::topFamilies($selectedPlots),
            'family_comparison' => self::familyComparison($selectedPlots),
            'herb_ivi' => self::herbIvi($selectedPlots),
            'wood_ivi' => self::woodIvi($selectedPlots),
            'ivi_comparison' => self::iviComparison($selectedPlots),
            default => null,
        };
    }

    public static function placeholder(string $key): ?array
    {
        return match ($key) {
            'all_traits' => ['key' => $key, 'title' => '類群×特性（全部）'],
            'alien_traits' => ['key' => $key, 'title' => '類群×特性（歸化）'],
            'habitat_shannon' => ['key' => $key, 'title' => '生育地多樣性指數'],
            'habitat_iv' => ['key' => $key, 'title' => '生育地歸化物種IV'],
            'herb_ivi' => ['key' => $key, 'title' => '草本小樣方歸化物種重要數值表'],
            'wood_ivi' => ['key' => $key, 'title' => '木本小樣方歸化物種重要數值表'],
            'ivi_comparison' => ['key' => $key, 'title' => '低海拔IVI比較'],
            'top_families' => ['key' => $key, 'title' => '歸化物種優勢科 Top 10', 'chartSpec' => ['type' => 'column']],
            'family_comparison' => ['key' => $key, 'title' => '低海拔外來植物優勢科比較圖', 'chartSpec' => ['type' => 'family-comparison']],
            default => null,
        };
    }

    private static function allTraits(array $selectedPlots): ?array
    {
        $all = FloraGroupStats::taxonLifeformSummaryByQuery(
            selectedPlots: $selectedPlots,
            mode: 'all'
        );

        if (empty($all['rows'])) {
            return null;
        }

        return [
            'title' => '類群×特性（全部）',
            'headings' => $all['headings'],
            'rows' => $all['rows'],
            'numberCols' => [],
            'fillEmptyWithZero' => true,
            'layouts' => ['row-groups', 'merge-a1b1'],
            'headerGroups' => [],
        ];
    }

    private static function alienTraits(array $selectedPlots): ?array
    {
        $alien = FloraGroupStats::taxonLifeformSummaryByQuery(
            selectedPlots: $selectedPlots,
            mode: 'alien-only',
            includeCultivated: false
        );

        if (empty($alien['rows'])) {
            return null;
        }

        return [
            'title' => '類群×特性（歸化）',
            'headings' => $alien['headings'],
            'rows' => $alien['rows'],
            'numberCols' => [],
            'fillEmptyWithZero' => true,
            'layouts' => ['row-groups', 'merge-a1b1'],
            'headerGroups' => [],
        ];
    }

    private static function habitatShannon(array $selectedPlots): ?array
    {
        $shannonRows = HabitatShannonIndex::buildHabitatShannonIndexByQuery(
            selectedPlots: $selectedPlots,
            logBase: 'e'
        );

        if (empty($shannonRows)) {
            return null;
        }

        return [
            'title' => '生育地多樣性指數',
            'headings' => null,
            'rows' => $shannonRows,
            'numberCols' => ['歸化種數比例(%)', '歸化物種平均覆蓋度(%)', 'Shannon_歸化', 'Shannon_原生', 'Shannon_全部'],
            'fillEmptyWithZero' => true,
            'layouts' => ['two-row-group-header'],
            'headerGroups' => ['Shannon_' => 'Shannon index'],
        ];
    }

    private static function habitatIv(array $selectedPlots): ?array
    {
        $habitatIv = HabitatIVIndex::alienImportanceTopNByQuery(
            selectedPlots: $selectedPlots,
            topN: 10,
            labelField: 'chname',
            includeCultivated: false
        );

        if (empty($habitatIv['rows'])) {
            return null;
        }

        return [
            'title' => '生育地歸化物種IV',
            'headings' => $habitatIv['headings'],
            'rows' => $habitatIv['rows'],
            'numberCols' => [],
            'fillEmptyWithZero' => false,
            'layouts' => ['rowWrap'],
            'headerGroups' => [],
        ];
    }

    private static function topFamilies(array $selectedPlots): ?array
    {
        $topFamilies = FloraChartData::topNaturalizedFamilies($selectedPlots, 10);
        if (empty($topFamilies['rows'])) {
            return null;
        }

        return [
            'title' => '歸化物種優勢科 Top 10',
            'headings' => $topFamilies['headings'],
            'rows' => $topFamilies['rows'],
            'numberCols' => [],
            'fillEmptyWithZero' => false,
            'layouts' => [],
            'headerGroups' => [],
            'chartSpec' => [
                'type' => 'column',
                'category' => '植物科名',
                'series' => [['name' => '物種數', 'value' => '物種數']],
                'legend' => 'none',
                'xTitle' => '科別',
                'yTitle' => '物種數',
                'position' => ['topLeft' => 'D2', 'bottomRight' => 'N24'],
            ],
        ];
    }

    private static function familyComparison(array $selectedPlots): ?array
    {
        $familyComparison = FloraChartData::lowElevationNaturalizedFamilyComparison($selectedPlots, 500, 15);
        if (empty($familyComparison['rows'])) {
            return null;
        }

        return [
            'title' => '低海拔外來植物優勢科比較圖',
            'headings' => $familyComparison['headings'],
            'rows' => $familyComparison['rows'],
            'countyLabel' => $familyComparison['countyLabel'] ?? '選取縣市',
            'plotCount' => (int) ($familyComparison['plotCount'] ?? 0),
            'numberCols' => [],
            'fillEmptyWithZero' => false,
            'layouts' => [],
            'headerGroups' => [],
            'chartSpec' => [
                'type' => 'family-comparison',
                'category' => '植物科名',
                'series' => [
                    ['name' => '前次調查', 'value' => '前次調查'],
                    ['name' => '本次調查', 'value' => '本次調查'],
                ],
                'legend' => 'bottom',
                'xTitle' => '科別',
                'yTitle' => '物種數',
                'position' => ['topLeft' => 'D2', 'bottomRight' => 'N24'],
            ],
        ];
    }

    private static function herbIvi(array $selectedPlots): array
    {
        $herbIvi = FloraIVISupport::iviTable(
            selectedPlots: $selectedPlots,
            habMode: 'herb',
            includeCultivated: false
        );

        return [
            'title' => '草本小樣方歸化物種重要數值表',
            'headings' => $herbIvi['headings'],
            'rows' => $herbIvi['rows'],
            'numberCols' => ['平均覆蓋度(%)' => 2, '相對覆蓋度(%)' => 2, '相對頻度(%)' => 2, 'IVI 重要值(%)' => 2],
            'fillEmptyWithZero' => false,
            'layouts' => ['showZeros'],
            'headerGroups' => [],
        ];
    }

    private static function woodIvi(array $selectedPlots): ?array
    {
        $wood08 = FloraIVISupport::iviTable(
            selectedPlots: $selectedPlots,
            habMode: 'wood-08',
            includeCultivated: false
        );
        $wood09 = FloraIVISupport::iviTable(
            selectedPlots: $selectedPlots,
            habMode: 'wood-09',
            includeCultivated: false
        );
        $woodRows = [];
        if (!empty($wood08['rows'])) {
            $woodRows[] = ['中文名' => '[天然林]'];
            $woodRows = array_merge($woodRows, $wood08['rows']);
        }
        if (!empty($wood09['rows'])) {
            $woodRows[] = ['中文名' => '[人工林]'];
            $woodRows = array_merge($woodRows, $wood09['rows']);
        }
        if (empty($woodRows)) {
            return null;
        }

        return [
            'title' => '木本小樣方歸化物種重要數值表',
            'headings' => $wood08['headings'] ?: $wood09['headings'],
            'rows' => $woodRows,
            'numberCols' => ['平均覆蓋度(%)' => 2, '相對覆蓋度(%)' => 2, '相對頻度(%)' => 2, 'IVI 重要值(%)' => 2],
            'fillEmptyWithZero' => false,
            'layouts' => ['showZeros', 'ivi-groups'],
            'headerGroups' => [],
        ];
    }

    private static function iviComparison(array $selectedPlots): ?array
    {
        $iviComparison = IviComparisonTable::build(
            selectedPlots: $selectedPlots,
            maxElevation: 500,
            minCurrentIvi: 1
        );
        if (empty($iviComparison['rows'])) {
            return null;
        }

        $countyLabel = (string) ($iviComparison['countyLabel'] ?? '選取縣市');
        return [
            'title' => $countyLabel . '低海拔IVI比較',
            'headings' => $iviComparison['headings'],
            'rows' => $iviComparison['rows'],
            'countyLabel' => $countyLabel,
            'plotCount' => (int) ($iviComparison['plotCount'] ?? 0),
            'numberCols' => [
                '本次調查_相對覆蓋度(%)' => 2,
                '本次調查_相對頻度(%)' => 2,
                '本次調查_IVI重要值(%)' => 2,
                '前次調查_相對覆蓋度(%)' => 2,
                '前次調查_相對頻度(%)' => 2,
                '前次調查_IVI重要值(%)' => 2,
            ],
            'fillEmptyWithZero' => false,
            'layouts' => ['ivi-comparison'],
            'headerGroups' => [],
        ];
    }
}