<?php

namespace App\Support;

class StatsTablesBuilder
{
    public static function build(array $selectedPlots): array
    {
        $sections = [];

        $all = FloraGroupStats::taxonLifeformSummaryByQuery(
            selectedPlots: $selectedPlots,
            mode: 'all'
        );
        if (!empty($all['rows'])) {
            $sections[] = [
                'title' => '類群×特性（全部）',
                'headings' => $all['headings'],
                'rows' => $all['rows'],
                'numberCols' => [],
                'fillEmptyWithZero' => true,
                'layouts' => ['row-groups', 'merge-a1b1'],
                'headerGroups' => [],
            ];
        }

        $alien = FloraGroupStats::taxonLifeformSummaryByQuery(
            selectedPlots: $selectedPlots,
            mode: 'alien-only',
            includeCultivated: false
        );
        if (!empty($alien['rows'])) {
            $sections[] = [
                'title' => '類群×特性（歸化）',
                'headings' => $alien['headings'],
                'rows' => $alien['rows'],
                'numberCols' => [],
                'fillEmptyWithZero' => true,
                'layouts' => ['row-groups', 'merge-a1b1'],
                'headerGroups' => [],
            ];
        }

        $shannonRows = HabitatShannonIndex::buildHabitatShannonIndexByQuery(
            selectedPlots: $selectedPlots,
            logBase: 'e'
        );
        if (!empty($shannonRows)) {
            $sections[] = [
                'title' => '生育地多樣性指數',
                'headings' => null,
                'rows' => $shannonRows,
                'numberCols' => ['歸化種數比例(%)', '歸化物種平均覆蓋度(%)', 'Shannon_歸化', 'Shannon_原生', 'Shannon_全部'],
                'fillEmptyWithZero' => true,
                'layouts' => ['two-row-group-header'],
                'headerGroups' => ['Shannon_' => 'Shannon index'],
            ];
        }

        $habitatIv = HabitatIVIndex::alienImportanceTopNByQuery(
            selectedPlots: $selectedPlots,
            topN: 10,
            labelField: 'chname',
            includeCultivated: false
        );
        if (!empty($habitatIv['rows'])) {
            $sections[] = [
                'title' => '生育地歸化物種IV',
                'headings' => $habitatIv['headings'],
                'rows' => $habitatIv['rows'],
                'numberCols' => [],
                'fillEmptyWithZero' => false,
                'layouts' => ['rowWrap'],
                'headerGroups' => [],
            ];
        }

        $topFamilies = FloraChartData::topNaturalizedFamilies($selectedPlots, 10);
        if (!empty($topFamilies['rows'])) {
            $sections[] = [
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

        $herbIvi = FloraIVISupport::iviTable(
            selectedPlots: $selectedPlots,
            habMode: 'herb',
            includeCultivated: false
        );
        $sections[] = [
            'title' => '草本小樣方歸化物種重要數值表',
            'headings' => $herbIvi['headings'],
            'rows' => $herbIvi['rows'],
            'numberCols' => ['平均覆蓋度(%)' => 2, '相對覆蓋度(%)' => 2, '相對頻度(%)' => 2, 'IVI 重要值(%)' => 2],
            'fillEmptyWithZero' => false,
            'layouts' => ['showZeros'],
            'headerGroups' => [],
        ];

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
        if (!empty($woodRows)) {
            $sections[] = [
                'title' => '木本小樣方歸化物種重要數值表',
                'headings' => $wood08['headings'] ?: $wood09['headings'],
                'rows' => $woodRows,
                'numberCols' => ['平均覆蓋度(%)' => 2, '相對覆蓋度(%)' => 2, '相對頻度(%)' => 2, 'IVI 重要值(%)' => 2],
                'fillEmptyWithZero' => false,
                'layouts' => ['showZeros', 'ivi-groups'],
                'headerGroups' => [],
            ];
        }


        $iviComparison = IviComparisonTable::build(
            selectedPlots: $selectedPlots,
            maxElevation: 500,
            minCurrentIvi: 1
        );
        if (!empty($iviComparison['rows'])) {
            $countyLabel = (string) ($iviComparison['countyLabel'] ?? '選取縣市');
            $sections[] = [
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

        return $sections;
    }
}
