<?php
namespace App\Services;

use App\Models\FixLog;

class DataSyncService
{
    public static function syncById(
        string $modelClass,
        array $originalData,
        array $newData,
        array $fields,
        array $createExtra = [],
        array $updateExtra = [],
        array $requiredFields = [], // ✅ 新增：新增時必填欄位
        string $userCode = ''
    ) : bool {
        $changed = false; // ✅ 用來追蹤有無變動
        $newIds = collect($newData)->pluck('id')->filter()->all();

        // 刪除：原本有但新資料沒有的
        foreach ($originalData as $old) {
          
            if (!in_array($old['id'], $newIds)) {
                $modelClass::where('id', $old['id'])->delete();
                $changed = true; // ✅ 有變動
            }
        }

        // 新增與更新
        foreach ($newData as $item) {
            $data = collect($item)->only($fields)->toArray();
// dd($data);
            if (empty($item['id'])) {
                // ✅ 新增前先檢查必填欄位
                $missingRequired = collect($requiredFields)->contains(function ($field) use ($item) {
                    return empty($item[$field]);
                });

                if (!$missingRequired) {
                    $modelClass::create($data + $createExtra);
                    $changed = true; // ✅ 有變動

                }
            } else {
                // 先取得原資料
                $existing = collect($originalData)->firstWhere('id', $item['id']);
                $diff = [];

                foreach ($data as $key => $value) {
                    if (($existing[$key] ?? null) != $value) {
                        $diff[$key] = [
                            'old' => $existing[$key] ?? null,
                            'new' => $value,
                        ];
                    }
                }
                // 檢查是否真的有差異
                if (!empty($diff)) {
                    $model = $modelClass::find($item['id']);
                    if ($model) {
                        $model->update($data + $updateExtra);
                        $changed = true;

                        // ✅ 記錄異動欄位
                        if ($userCode) {
                            FixLog::create([
                                'table_name' => $model->getTable(),
                                'record_id' => $model->getKey(),
                                'changes' => $diff,
                                'modified_by' => $userCode,
                                'modified_at' => now(),
                            ]);
                        }
                    }
                }
            }
        }

        return $changed; // ✅ 回傳有無改動
    }

}
