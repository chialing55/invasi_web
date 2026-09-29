<?php

namespace App\Livewire;

use App\Models\HabitatInfo;
use App\Models\PlotHab;
use App\Models\PlotHabitatCompletionException;
use App\Models\PlotList2025;
use App\Models\SubPlotEnv2025;
use App\Support\HabitatCode;
use App\Support\PlanYearPlotFilter;
use App\Support\RecordStateGuard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class EntryHabitatCompletionException extends Component
{
    public array $censusYearList = [];

    public array $countyList = [];

    public array $plotList = [];

    public array $rows = [];

    public string $thisCensusYear = '';

    public string $thisCounty = '';

    public string $thisPlot = '';

    public string $userOrg = '';

    public string $creatorCode = '';

    public string $stateToken = '';

    public function mount(): mixed
    {
        $user = Auth::user();
        if (! $user) {
            return redirect('/');
        }

        $this->userOrg = (string) ($user->organization ?? '');
        $this->creatorCode = explode('@', (string) $user->email)[0];
        $this->censusYearList = PlanYearPlotFilter::years($user);
        $this->thisCensusYear = PlanYearPlotFilter::defaultYear($this->censusYearList);
        $this->refreshCounties();

        return null;
    }

    public function loadThisCensusYearData($value): void
    {
        $this->thisCensusYear = (string) $value;
        $this->thisCounty = '';
        $this->thisPlot = '';
        $this->plotList = [];
        $this->rows = [];
        $this->stateToken = '';
        $this->refreshCounties();
    }

    public function loadPlots($county): void
    {
        $this->thisCounty = (string) $county;
        $this->thisPlot = '';
        $this->rows = [];
        $this->stateToken = '';

        if ($this->thisCounty === '') {
            $this->plotList = [];

            return;
        }

        $this->plotList = PlanYearPlotFilter::plots(
            Auth::user(),
            $this->thisCensusYear,
            $this->thisCounty
        );
    }

    public function loadPlotInfo($plot): void
    {
        $this->thisPlot = (string) $plot;
        $this->rows = [];
        $this->stateToken = '';

        if ($this->thisPlot === '') {
            return;
        }

        $plotRow = $this->accessiblePlotRow();
        if (! $plotRow) {
            $this->thisPlot = '';
            throw ValidationException::withMessages(['thisPlot' => '無權限查看此樣區，或樣區不屬於所選年度。']);
        }

        $selectedHabitats = PlotHab::query()
            ->where('plot', $this->thisPlot)
            ->orderBy('habitat_code')
            ->get();
        $selectedCodes = $selectedHabitats
            ->pluck('habitat_code')
            ->map(fn ($code) => str_pad((string) $code, 2, '0', STR_PAD_LEFT))
            ->unique()
            ->sort()
            ->values();

        $labels = HabitatInfo::pluck('habitat', 'habitat_code')->toArray();
        $counts = SubPlotEnv2025::query()
            ->where('plot', $this->thisPlot)
            ->selectRaw('habitat_code, COUNT(DISTINCT plot_full_id) AS subplot_count')
            ->groupBy('habitat_code')
            ->pluck('subplot_count', 'habitat_code');
        $exceptionRecords = PlotHabitatCompletionException::query()
            ->where('plot', $this->thisPlot)
            ->orderBy('habitat_code')
            ->get();
        $exceptions = $exceptionRecords->keyBy('habitat_code');
        $this->stateToken = $this->completionStateToken($selectedHabitats, $exceptionRecords);

        foreach ($this->canonicalGroups($selectedCodes->all()) as $groupKey => $codes) {
            $existing = collect($codes)->map(fn ($item) => $exceptions->get($item))->filter();
            $savedCount = $existing->first()?->actual_subplot_count;

            $this->rows[] = [
                'group_key' => $groupKey,
                'codes' => $codes,
                'label' => collect($codes)
                    ->map(fn ($item) => $item.' '.($labels[$item] ?? $item))
                    ->implode('／'),
                'enabled' => $existing->isNotEmpty(),
                'actual_subplot_count' => $savedCount !== null ? (string) $savedCount : '',
                'current_counts' => collect($codes)
                    ->mapWithKeys(fn ($item) => [$item => (int) ($counts[$item] ?? 0)])
                    ->all(),
            ];
        }
    }

    public function save(): void
    {
        $plotRow = $this->accessiblePlotRow();
        if (! $plotRow) {
            throw ValidationException::withMessages(['thisPlot' => '無權限修改此樣區，或樣區不屬於所選年度。']);
        }

        DB::connection('invasiflora')->transaction(function () use ($plotRow) {
            $lockedPlot = $this->accessiblePlotQuery()
                ->whereKey($plotRow->getKey())
                ->where('plot', $this->thisPlot)
                ->lockForUpdate()
                ->first();
            if (! $lockedPlot) {
                throw ValidationException::withMessages([
                    'thisPlot' => '樣區資料已變更，請重新選擇樣區後再儲存。',
                ]);
            }

            $selectedHabitats = PlotHab::query()
                ->where('plot', $this->thisPlot)
                ->orderBy('habitat_code')
                ->lockForUpdate()
                ->get();
            $exceptionRecords = PlotHabitatCompletionException::query()
                ->where('plot', $this->thisPlot)
                ->orderBy('habitat_code')
                ->lockForUpdate()
                ->get();

            RecordStateGuard::assertToken(
                $this->stateToken,
                'habitat-completion:'.$this->thisPlot,
                $this->completionState($selectedHabitats, $exceptionRecords),
                'thisPlot',
                '生育地或核定門檻已由其他分頁變更，請重新選擇樣區後再儲存。'
            );

            $selectedCodes = $selectedHabitats
                ->pluck('habitat_code')
                ->map(fn ($code) => str_pad((string) $code, 2, '0', STR_PAD_LEFT))
                ->unique()
                ->sort()
                ->values()
                ->all();
            $canonicalGroups = $this->canonicalGroups($selectedCodes);
            $submittedRows = $this->validatedSubmittedRows($canonicalGroups);

            PlotHabitatCompletionException::query()
                ->where('plot', $this->thisPlot)
                ->whereNotIn('habitat_code', $selectedCodes)
                ->delete();

            foreach ($canonicalGroups as $groupKey => $codes) {
                $submitted = $submittedRows[$groupKey];

                foreach ($codes as $code) {
                    $key = [
                        'plot' => $this->thisPlot,
                        'habitat_code' => $code,
                    ];

                    if (! $submitted['enabled']) {
                        PlotHabitatCompletionException::where($key)->delete();

                        continue;
                    }

                    $record = PlotHabitatCompletionException::firstOrNew($key);
                    $record->team = (string) $plotRow->team;
                    $record->county = (string) $plotRow->county;
                    $record->actual_subplot_count = $submitted['actual_subplot_count'];
                    $record->updated_by = $this->creatorCode;
                    if (! $record->exists) {
                        $record->created_by = $this->creatorCode;
                    }
                    $record->save();
                }
            }
        });

        $this->loadPlotInfo($this->thisPlot);
        session()->flash('habitatExceptionMessage', '生育地完成例外已儲存。');
    }

    /**
     * @param  array<string, array<int, string>>  $canonicalGroups
     * @return array<string, array{enabled: bool, actual_subplot_count: ?int}>
     */
    private function validatedSubmittedRows(array $canonicalGroups): array
    {
        $submittedRows = [];

        foreach ($this->rows as $index => $row) {
            $groupKey = $row['group_key'] ?? null;
            if (! is_string($groupKey)
                || ! array_key_exists($groupKey, $canonicalGroups)
                || array_key_exists($groupKey, $submittedRows)) {
                throw ValidationException::withMessages([
                    'thisPlot' => '生育地清單已變更，請重新選擇樣區後再儲存。',
                ]);
            }

            $enabled = (bool) ($row['enabled'] ?? false);
            $count = filter_var($row['actual_subplot_count'] ?? null, FILTER_VALIDATE_INT);
            if ($enabled && ($count === false || $count < 1 || $count > 4)) {
                throw ValidationException::withMessages([
                    "rows.{$index}.actual_subplot_count" => '可調查數量須為 1 至 4 的整數。',
                ]);
            }

            $submittedRows[$groupKey] = [
                'enabled' => $enabled,
                'actual_subplot_count' => $enabled ? (int) $count : null,
            ];
        }

        if (array_keys($submittedRows) !== array_keys($canonicalGroups)) {
            throw ValidationException::withMessages([
                'thisPlot' => '生育地清單已變更，請重新選擇樣區後再儲存。',
            ]);
        }

        return $submittedRows;
    }

    private function completionStateToken($selectedHabitats, $exceptionRecords): string
    {
        return RecordStateGuard::token(
            'habitat-completion:'.$this->thisPlot,
            $this->completionState($selectedHabitats, $exceptionRecords)
        );
    }

    private function completionState($selectedHabitats, $exceptionRecords): array
    {
        return [
            'habitats' => RecordStateGuard::snapshot($selectedHabitats, ['habitat_code']),
            'exceptions' => RecordStateGuard::snapshot($exceptionRecords, ['habitat_code', 'actual_subplot_count']),
        ];
    }

    /**
     * Build the authoritative save groups from the habitats selected for the plot.
     * The browser may identify a group, but it never decides which habitat codes
     * belong to that group.
     *
     * @param  array<int, int|string>  $selectedCodes
     * @return array<string, array<int, string>>
     */
    private function canonicalGroups(array $selectedCodes): array
    {
        $selectedCodes = collect($selectedCodes)
            ->map(fn ($code) => str_pad((string) $code, 2, '0', STR_PAD_LEFT))
            ->unique()
            ->sort()
            ->values()
            ->all();
        $selectedLookup = array_fill_keys($selectedCodes, true);
        $processed = [];
        $groups = [];

        foreach ($selectedCodes as $code) {
            if (isset($processed[$code])) {
                continue;
            }

            $codes = [$code];
            if (HabitatCode::isWood($code)) {
                $understory = HabitatCode::understoryFor($code);
                if ($understory !== null && isset($selectedLookup[$understory])) {
                    $codes[] = $understory;
                }
            } elseif (HabitatCode::isUnderstory($code)) {
                $main = HabitatCode::mainFor($code);
                if ($main !== null && isset($selectedLookup[$main])) {
                    continue;
                }
            }

            foreach ($codes as $groupCode) {
                $processed[$groupCode] = true;
            }

            $groups[implode('+', $codes)] = $codes;
        }

        return $groups;
    }

    private function refreshCounties(): void
    {
        $this->countyList = PlanYearPlotFilter::counties(Auth::user(), $this->thisCensusYear);
    }

    private function accessiblePlotQuery()
    {
        $user = Auth::user();

        return PlanYearPlotFilter::query($user, $this->thisCensusYear);
    }

    private function accessiblePlotRow(): ?PlotList2025
    {
        if ($this->thisPlot === '') {
            return null;
        }

        return $this->accessiblePlotQuery()
            ->where('plot', $this->thisPlot)
            ->orderByDesc('census_year')
            ->first();
    }

    public function render()
    {
        return view('livewire.entry-habitat-completion-exception');
    }
}
