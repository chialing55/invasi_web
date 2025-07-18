<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use App\Exports\PlotExport;

class MultiSheetExport implements WithMultipleSheets
{
    protected $envdata;
    protected $plantdata;
    protected $format;

    public function __construct(array $envdata, array $plantdata, string $format)
    {
        $this->envdata = $envdata;
        $this->plantdata = $plantdata;
        $this->format = $format;
    }

    public function sheets(): array
    {
        // dd($this->plantdata);
        return [
            new PlotExport($this->envdata, $this->format, '環境資料'),
            new PlotExport($this->plantdata, $this->format, '植物資料'),
        ];
    }
}
