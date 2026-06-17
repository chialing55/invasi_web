<?php

namespace App\Livewire;

use App\Models\PlotList2025;
use App\Models\SubPlotEnv2025;
use App\Support\StatsTablesBuilder;
use Livewire\Component;

class ResultsCharts extends Component
{
    public array $yearList = [];
    public array $teamList = [];
    public array $countyList = [];
    public string $thisCensusYear = '';
    public string $thisTeam = '';
    public string $thisCounty = '';
    public array $selectedPlots = [];
    public array $sections = [];
    public array $loadedSections = [];
    public array $openSections = [];
    public string $message = '';

    public function mount(): void
    {
        $this->yearList = PlotList2025::where('census_year', '>=', 2025)
            ->distinct()
            ->orderByDesc('census_year')
            ->pluck('census_year')
            ->toArray();
        $this->teamList = PlotList2025::select('team')->distinct()->pluck('team')->filter()->values()->toArray();
        $this->thisCensusYear = (string) date('Y');
        $this->loadCountyList('');
    }

    public function loadYear($year): void
    {
        $this->thisCensusYear = $year === 'All' ? '' : (string) $year;
        $this->loadCountyList($this->thisTeam);
    }

    public function loadCountyList($team): void
    {
        $team = $team === 'All' ? '' : (string) $team;
        $this->thisTeam = $team;
        $this->thisCounty = '';
        $this->selectedPlots = [];
        $this->sections = [];
        $this->loadedSections = [];
        $this->openSections = [];
        $this->message = '';

        $this->countyList = PlotList2025::query()
            ->when($this->thisCensusYear !== '', fn($q) => $q->where('census_year', $this->thisCensusYear))
            ->when($team !== '', fn($q) => $q->where('team', $team))
            ->select('county')
            ->distinct()
            ->orderBy('county')
            ->pluck('county')
            ->filter()
            ->values()
            ->toArray();
    }

    public function loadResultsForCounty($county): void
    {
        $this->thisCounty = $county === 'All' ? '' : (string) $county;
        $this->selectedPlots = $this->querySelectedPlots();
        $this->loadedSections = [];
        $this->openSections = [];

        if (empty($this->selectedPlots)) {
            $this->sections = [];
            $this->message = '尚未有調查資料。';
            return;
        }

        $this->sections = $this->sectionPlaceholders();
        $this->message = '';
    }

    public function toggleSection(string $key): void
    {
        $isOpen = !($this->openSections[$key] ?? false);
        $this->openSections[$key] = $isOpen;

        if ($isOpen && !isset($this->loadedSections[$key])) {
            $this->loadSection($key);
            return;
        }

        $this->dispatchOpenCharts();
    }

    public function isOpen(string $key): bool
    {
        return (bool) ($this->openSections[$key] ?? false);
    }

    public function displaySection(array $section): array
    {
        return $this->loadedSections[$section['displayKey']] ?? $section;
    }

    private function loadSection(string $displayKey): void
    {
        $meta = collect($this->sections)->firstWhere('displayKey', $displayKey);
        if (!$meta || empty($this->selectedPlots)) {
            return;
        }

        $cacheKey = $this->sectionCacheKey((string) $meta['sourceKey']);
        $section = session()->get($cacheKey);
        if (!is_array($section)) {
            $raw = StatsTablesBuilder::buildOne((string) $meta['sourceKey'], $this->selectedPlots);
            $section = $raw ? $this->decorateLoadedSection($raw, $meta) : $this->emptyLoadedSection($meta);
            session()->put($cacheKey, $section);
        }

        $this->loadedSections[$displayKey] = $section;
        $this->dispatchOpenCharts();
    }

    private function dispatchOpenCharts(): void
    {
        foreach ($this->loadedSections as $displayKey => $section) {
            if (!($this->openSections[$displayKey] ?? false)) {
                continue;
            }

            $this->dispatchChartIfNeeded($section);
        }
    }

    private function dispatchChartIfNeeded(array $section): void
    {
        if (empty($section['isFigure']) || empty($section['rows'])) {
            return;
        }

        $this->dispatch('results-chart-ready', chart: $this->chartPayload($section));
    }

    private function chartPayload(array $section): array
    {
        $type = (string) ($section['chartSpec']['type'] ?? 'column');
        $labels = [];
        $previous = [];
        $current = [];
        $values = [];

        foreach ($section['rows'] ?? [] as $row) {
            $row = (array) $row;
            $labels[] = (string) ($row['植物科名'] ?? '');
            if ($type === 'family-comparison') {
                $previous[] = (int) ($row['前次調查'] ?? 0);
                $current[] = (int) ($row['本次調查'] ?? 0);
            } else {
                $values[] = (int) ($row['物種數'] ?? 0);
            }
        }

        return [
            'id' => 'results-chart-' . $section['displayKey'],
            'type' => $type,
            'labels' => $labels,
            'datasets' => $type === 'family-comparison'
                ? [
                    ['label' => '前次調查', 'data' => $previous, 'backgroundColor' => '#16697a'],
                    ['label' => '本次調查', 'data' => $current, 'backgroundColor' => '#FF9905'],
                ]
                : [
                    ['label' => '物種數', 'data' => $values, 'backgroundColor' => '#FF9905'],
                ],
        ];
    }

    private function querySelectedPlots(): array
    {
        return SubPlotEnv2025::query()
            ->select('im_splotdata_2025.plot as plot')
            ->join('plot_list', 'im_splotdata_2025.plot', '=', 'plot_list.plot')
            ->when($this->thisCensusYear !== '', fn($q) => $q->where('plot_list.census_year', $this->thisCensusYear))
            ->when($this->thisTeam !== '', fn($q) => $q->where('plot_list.team', $this->thisTeam))
            ->when($this->thisCounty !== '', fn($q) => $q->where('plot_list.county', $this->thisCounty))
            ->distinct()
            ->pluck('plot')
            ->filter()
            ->values()
            ->toArray();
    }

    private function sectionPlaceholders(): array
    {
        $sections = [];
        $tableNo = 1;
        foreach (StatsTablesBuilder::tableKeys() as $key) {
            $placeholder = StatsTablesBuilder::placeholder($key);
            if ($placeholder === null) {
                continue;
            }
            $sections[] = $this->decoratePlaceholder($placeholder, 'table', $tableNo++);
        }

        $figureNo = 1;
        foreach (StatsTablesBuilder::figureKeys() as $key) {
            $placeholder = StatsTablesBuilder::placeholder($key);
            if ($placeholder === null) {
                continue;
            }
            $sections[] = $this->decoratePlaceholder($placeholder, 'figure', $figureNo++);
        }

        return $sections;
    }

    private function decoratePlaceholder(array $section, string $type, int $number): array
    {
        $section = $this->normalizeSection($section + ['rows' => [], 'headings' => [], 'numberCols' => [], 'layouts' => [], 'headerGroups' => []]);
        $section['sourceKey'] = $section['key'];
        $section['displayKey'] = $type . '-' . $number;
        $section['displayNo'] = $number;
        $section['isFigure'] = $type === 'figure';
        $section['isLoaded'] = false;
        $section['displayTitle'] = ($type === 'figure' ? '圖 ' : '表 ') . $number . ' ' . ($type === 'figure' ? $this->figureTitle($section) : $this->tableTitle($section));
        $section['displayHeadings'] = [];

        return $section;
    }

    private function decorateLoadedSection(array $section, array $meta): array
    {
        $section = $this->normalizeSection($section);
        $section['sourceKey'] = $meta['sourceKey'];
        $section['displayKey'] = $meta['displayKey'];
        $section['displayNo'] = $meta['displayNo'];
        $section['isFigure'] = (bool) $meta['isFigure'];
        $section['isLoaded'] = true;
        $section['displayTitle'] = ($section['isFigure'] ? '圖 ' : '表 ') . $section['displayNo'] . ' ' . ($section['isFigure'] ? $this->figureTitle($section) : $this->tableTitle($section));
        $section['displayHeadings'] = $this->headingsFor($section);

        return $section;
    }

    private function emptyLoadedSection(array $meta): array
    {
        $section = $meta;
        $section['rows'] = [];
        $section['displayHeadings'] = [];
        $section['isLoaded'] = true;
        $section['emptyMessage'] = '無資料';

        return $section;
    }

    private function sectionCacheKey(string $sourceKey): string
    {
        return 'results-charts.v3.' . md5(json_encode([
            'year' => $this->thisCensusYear,
            'team' => $this->thisTeam,
            'county' => $this->thisCounty,
            'plots' => $this->selectedPlots,
            'section' => $sourceKey,
        ], JSON_UNESCAPED_UNICODE));
    }

    private function normalizeSection(array $section): array
    {
        $section['headings'] = isset($section['headings']) && is_array($section['headings'])
            ? array_values($section['headings'])
            : $section['headings'] ?? null;
        $section['rows'] = array_map(fn($row) => $this->normalizeRow($row), $section['rows'] ?? []);
        $section['numberCols'] = $this->normalizeValue($section['numberCols'] ?? []);
        $section['layouts'] = $this->normalizeValue($section['layouts'] ?? []);
        $section['headerGroups'] = $this->normalizeValue($section['headerGroups'] ?? []);
        if (isset($section['chartSpec'])) {
            $section['chartSpec'] = $this->normalizeValue($section['chartSpec']);
        }

        return $section;
    }

    private function normalizeRow(mixed $row): array
    {
        $row = (array) $row;
        foreach ($row as $key => $value) {
            $row[$key] = $this->normalizeValue($value);
        }

        return $row;
    }

    private function normalizeValue(mixed $value): mixed
    {
        if (is_object($value) && method_exists($value, 'getPlainText')) {
            return $value->getPlainText();
        }

        if (is_array($value)) {
            $normalized = [];
            foreach ($value as $key => $item) {
                $normalized[$key] = $this->normalizeValue($item);
            }
            return $normalized;
        }

        if (is_object($value)) {
            if (method_exists($value, '__toString')) {
                return (string) $value;
            }
            return $this->normalizeValue((array) $value);
        }

        return $value;
    }

    private function headingsFor(array $section): array
    {
        if (!empty($section['headings']) && is_array($section['headings'])) {
            return $section['headings'];
        }

        $first = (array) ($section['rows'][0] ?? []);
        return array_keys($first);
    }

    private function tableTitle(array $section): string
    {
        $county = $this->countyLabel();

        return match ((string) ($section['title'] ?? '')) {
            '類群×特性（全部）' => $county . '調查記錄之全部植物習性統計',
            '類群×特性（歸化）' => $county . '調查記錄之歸化物種習性統計',
            '生育地多樣性指數' => $county . '地區各生育地類型之原生、歸化物種各項統計一覽表',
            '生育地歸化物種IV' => $county . '地區各生育地歸化物種（依重要值排序）',
            '草本小樣方歸化物種重要數值表' => $county . '地區草本小樣方之歸化物種重要數值一覽表（依 IVI 重要值排序）',
            '木本小樣方歸化物種重要數值表' => $county . '地區木本小樣方之歸化物種重要數值一覽表（依 IVI 重要值排序）',
            default => $this->dynamicSectionTitle($section),
        };
    }

    private function figureTitle(array $section): string
    {
        $county = $this->countyLabel();

        return match ((string) ($section['title'] ?? '')) {
            '歸化物種優勢科 Top 10' => $county . '地區歸化物種優勢科前十名排名圖',
            '低海拔外來植物優勢科比較圖' => '針對' . (string) ($section['countyLabel'] ?? $county) . '海拔500 m以下的' . ((int) ($section['plotCount'] ?? 0) > 0 ? (int) ($section['plotCount'] ?? 0) . '處' : '') . '平地樣區，比較前次與本次調查外來植物優勢科的排序與變化情形。',
            default => (string) ($section['title'] ?? ''),
        };
    }

    private function dynamicSectionTitle(array $section): string
    {
        $title = (string) ($section['title'] ?? '');
        if (str_ends_with($title, '低海拔IVI比較')) {
            $county = (string) ($section['countyLabel'] ?? $this->countyLabel());
            $plotCount = (int) ($section['plotCount'] ?? 0);
            return '針對' . $county . '海拔500 m以下的' . ($plotCount > 0 ? $plotCount . '處' : '') . '平地樣區，比較本次與前次調查IVI佔比達1%以上物種的優勢度排序情形。';
        }

        return $title;
    }

    private function countyLabel(): string
    {
        if ($this->thisCounty !== '') {
            return $this->thisCounty;
        }

        return $this->thisTeam !== '' ? $this->thisTeam . '團隊全部縣市' : '全部縣市';
    }

    public function render()
    {
        return view('livewire.results-charts');
    }
}