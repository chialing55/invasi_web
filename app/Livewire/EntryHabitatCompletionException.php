<?php

namespace App\Livewire;

use App\Models\HabitatInfo;
use App\Models\PlotHab;
use App\Models\PlotHabitatCompletionException;
use App\Models\PlotList2025;
use App\Models\SubPlotEnv2025;
use App\Support\HabitatCode;
use App\Support\PlanYearPlotFilter;
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
        $this->refreshCounties();
    }

    public function loadPlots($county): void
    {
        $this->thisCounty = (string) $county;
        $this->thisPlot = '';
        $this->rows = [];

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

        if ($this->thisPlot === '') {
            return;
        }

        $plotRow = $this->accessiblePlotRow();
        if (! $plotRow) {
            $this->thisPlot = '';
            throw ValidationException::withMessages(['thisPlot' => '無權限查看此樣區，或樣區不屬於所選年度。']);
        }

        $selectedCodes = PlotHab::query()
            ->where('plot', $this->thisPlot)
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
        $exceptions = PlotHabitatCompletionException::query()
            ->where('plot', $this->thisPlot)
            ->get()
            ->keyBy('habitat_code');

        $processed = [];
        foreach ($selectedCodes as $code) {
            if (in_array($code, $processed, true)) {
                continue;
            }

            $codes = [$code];
            if (HabitatCode::isWood($code)) {
                $understory = HabitatCode::understoryFor($code);
                if ($understory !== null && $selectedCodes->contains($understory)) {
                    $codes[] = $understory;
                }
            } elseif (HabitatCode::isUnderstory($code)) {
                $main = HabitatCode::mainFor($code);
                if ($main !== null && $selectedCodes->contains($main)) {
                    continue;
                }
            }

            array_push($processed, ...$codes);
            $existing = collect($codes)->map(fn ($item) => $exceptions->get($item))->filter();
            $savedCount = $existing->first()?->actual_subplot_count;

            $this->rows[] = [
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

        $selectedCodes = PlotHab::query()
            ->where('plot', $this->thisPlot)
            ->pluck('habitat_code')
            ->map(fn ($code) => str_pad((string) $code, 2, '0', STR_PAD_LEFT))
            ->all();

        foreach ($this->rows as $index => $row) {
            $enabled = (bool) ($row['enabled'] ?? false);
            $count = filter_var($row['actual_subplot_count'] ?? null, FILTER_VALIDATE_INT);
            if ($enabled && ($count === false || $count < 1 || $count > 4)) {
                throw ValidationException::withMessages([
                    "rows.{$index}.actual_subplot_count" => '可調查數量須為 1 至 4 的整數。',
                ]);
            }

            foreach (($row['codes'] ?? []) as $code) {
                if (! in_array($code, $selectedCodes, true)) {
                    throw ValidationException::withMessages(['thisPlot' => '生育地清單已變更，請重新選擇樣區後再儲存。']);
                }
            }
        }

        DB::connection('invasiflora')->transaction(function () use ($plotRow) {
            foreach ($this->rows as $row) {
                foreach ($row['codes'] as $code) {
                    $key = [
                        'plot' => $this->thisPlot,
                        'habitat_code' => $code,
                    ];

                    if (! (bool) ($row['enabled'] ?? false)) {
                        PlotHabitatCompletionException::where($key)->delete();

                        continue;
                    }

                    $record = PlotHabitatCompletionException::firstOrNew($key);
                    $record->team = (string) $plotRow->team;
                    $record->county = (string) $plotRow->county;
                    $record->actual_subplot_count = (int) $row['actual_subplot_count'];
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
