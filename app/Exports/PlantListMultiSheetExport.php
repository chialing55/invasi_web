<?php
// =============================================================================
// File: app/Exports/PlantListMultiSheetExport.php
// 目的：組裝多個工作表
// =============================================================================

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use App\Exports\PlantListTableExport;
use App\Exports\PlantListExport;

class PlantListMultiSheetExport implements WithMultipleSheets
{
    public function __construct(
        protected array $selectedPlots,
        protected string $format = 'xlsx'
    ) {}

    public function sheets(): array
    {
        $sheets = [];

        // A) 類群×特性（全部）
        $all = PlantListExport::PlantListAll(
            selectedPlots: $this->selectedPlots,
            format: $this->format,
        );
        if (!empty($all['rows'])) {
            $sheets[] = new PlantListTableExport(
                rows: $all['rows'],
                title: '類群×特性（全部）',
                headings: $all['headings'],
                // 可選 layout：family-merge / row-groups / merge-a1b1 / none
                layouts: ['family-merge']
            );
        }

        // // B) 名錄（所選樣區）
        // $sel = PlantListExport::PlantListDistinctForPlots(
        //     selectedPlots: $this->selectedPlots,
        //     format: $this->format
        // );
        // if (!empty($sel['rows'])) {
        //     $sheets[] = new PlantListTableExport(
        //         rows: $sel['rows'],
        //         title: '名錄（所選樣區）',
        //         headings: $sel['headings'],
        //         layouts: 'family-merge'
        //     );
        // }

        // // C) 棲地代碼（所選樣區，01~20）
        // $hab = PlantListExport::PlantListHabitatPivot(
        //     selectedPlots: $this->selectedPlots,
        //     format: $this->format
        // );
        // if (!empty($hab['rows'])) {
        //     $sheets[] = new PlantListTableExport(
        //         rows: $hab['rows'],
        //         title: '棲地代碼（01~20）',
        //         headings: $hab['headings'],
        //         layouts: 'family-merge'
        //     );
        // }

        // D) 棲地代碼 + 群組列（需要 __pg/__fam/__chfam）
        $habG = PlantListExport::PlantListHabitatPivotWithGroups(
            selectedPlots: $this->selectedPlots,
            format: $this->format,
            limitBySelectedPlots : false,
        );
        if (!empty($habG['rows'])) {
            $sheets[] = new PlantListTableExport(
                rows: $habG['rows'],
                title: '棲地代碼（含群組列）',
                headings: $habG['headings'],
                // 這個 layout 會插入【類群】與 family 標頭列，並自動隱藏 __pg/__fam/__chfam
                layouts: ['pg-groups']
            );
        }

        return $sheets;
    }
}
?>