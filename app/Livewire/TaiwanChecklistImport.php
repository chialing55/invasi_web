<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\WithFileUploads;
use Throwable;

class TaiwanChecklistImport extends Component
{
    use WithFileUploads;

    public $csvFile;
    public string $message = '';
    public string $messageType = 'info';

    private string $connection = 'invasiflora';
    private string $table = 'taiwan_checklist';

    public function import(): void
    {
        if (Auth::user()?->role !== 'admin') {
            abort(403);
        }

        $this->resetMessage();

        $this->validate([
            'csvFile' => 'required|file|mimes:csv,txt|max:20480',
        ], [
            'csvFile.required' => '請先選擇 CSV 檔。',
            'csvFile.file' => '檔案格式不正確。',
            'csvFile.mimes' => '只接受 CSV 或純文字檔。',
            'csvFile.max' => '檔案不可超過 20 MB。',
        ]);

        if (!Schema::connection($this->connection)->hasTable($this->table)) {
            $this->fail("找不到資料表 {$this->table}。");
            return;
        }

        $tableColumns = Schema::connection($this->connection)->getColumnListing($this->table);
        $importColumns = array_values(array_diff($tableColumns, ['id']));

        try {
            [$headers, $rows] = $this->readCsv($this->csvFile->getRealPath());
            $this->assertHeadersMatch($headers, $importColumns, ["plantgroup"]);

            if (empty($rows)) {
                $this->fail('CSV 沒有可匯入的資料列。');
                return;
            }

            $imported = 0;

            DB::connection($this->connection)->transaction(function () use ($rows, $headers, $importColumns, &$imported) {
                DB::connection($this->connection)->table($this->table)->delete();

                foreach (array_chunk($rows, 500) as $chunk) {
                    $payload = array_map(function (array $row) use ($headers, $importColumns) {
                        $assoc = array_combine($headers, $row);
                        $record = [];

                        foreach ($importColumns as $column) {
                            $value = $assoc[$column] ?? null;
                            $record[$column] = $value === '' ? null : $value;
                        }

                        return $record;
                    }, $chunk);

                    DB::connection($this->connection)->table($this->table)->insert($payload);
                    $imported += count($payload);
                }
            });

            $this->csvFile = null;
            $this->messageType = 'success';
            $this->message = "清空後匯入完成，共匯入 {$imported} 筆資料。";
        } catch (Throwable $e) {
            $this->fail('匯入失敗：' . $e->getMessage());
        }
    }

    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new \RuntimeException('無法讀取上傳檔案。');
        }

        $headers = null;
        $rows = [];

        while (($line = fgetcsv($handle)) !== false) {
            if ($line === [null] || $this->isBlankRow($line)) {
                continue;
            }

            $line = array_map(fn ($value) => is_string($value) ? trim($value) : $value, $line);

            if ($headers === null) {
                $headers = array_map(fn ($header) => $this->normalizeCsvHeader((string) $header), $line);
                continue;
            }

            if (count($line) !== count($headers)) {
                throw new \RuntimeException('CSV 欄位數與表頭不一致，請檢查資料列。');
            }

            $rows[] = $line;
        }

        fclose($handle);

        if ($headers === null) {
            throw new \RuntimeException('CSV 沒有表頭列。');
        }

        return [$headers, $rows];
    }

    private function normalizeCsvHeader(string $header): string
    {
        $header = preg_replace('/^\xEF\xBB\xBF/', '', trim($header));
        $header = trim($header, "\"'");

        return match ($header) {
            'export_date' => 'imported_at',
            'plant_group' => 'plantgroup',
            default => $header,
        };
    }

    private function assertHeadersMatch(array $headers, array $tableColumns, array $optionalColumns = []): void
    {
        $requiredColumns = array_values(array_diff($tableColumns, $optionalColumns));
        $missing = array_values(array_diff($requiredColumns, $headers));
        $extra = array_values(array_diff($headers, $tableColumns));

        if (!empty($missing) || !empty($extra)) {
            $messages = [];

            if (!empty($missing)) {
                $messages[] = '缺少欄位：' . implode(', ', $missing);
            }

            if (!empty($extra)) {
                $messages[] = '多出欄位：' . implode(', ', $extra);
            }

            throw new \RuntimeException(implode('；', $messages));
        }
    }

    private function isBlankRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function resetMessage(): void
    {
        $this->message = '';
        $this->messageType = 'info';
    }

    private function fail(string $message): void
    {
        $this->messageType = 'error';
        $this->message = $message;
    }

    public function render()
    {
        return view('livewire.taiwan-checklist-import');
    }
}
