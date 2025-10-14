<?php
namespace App\Services;

use App\Models\FixLog;
use App\Models\SpcodeIndex;
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

        // 刪除：原本有但新資料沒有的  //SpcodeIndex, subPlotPlant2025
        foreach ($originalData as $old) {
            if (!in_array($old['id'], $newIds)) {
                $model = $modelClass::find($old['id']);

                if ($model) {
                    // ✅ 若是 SpcodeIndex，記錄 FixLog
                    if ($modelClass === SpcodeIndex::class && $userCode) {
                        FixLog::create([
                            'table_name' => $model->getTable(),
                            'record_id' => $model->getKey(),
                            'changes' => [
                                '_deleted' => [
                                    'spcode' => $model->spcode,
                                    'chname_index' => $model->chname_index,
                                ]
                            ],
                            'modified_by' => $userCode,
                            'modified_at' => now(),
                        ]);
                    }

                    $model->delete();
                    $changed = true;
                }
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
                    $newModel = $modelClass::create($data + $createExtra);
                    $changed = true; // ✅ 有變動

                    // ✅ 若是 SpcodeIndex，記錄 FixLog
                    if ($modelClass === SpcodeIndex::class && $userCode) {
                        FixLog::create([
                            'table_name' => $newModel->getTable(),
                            'record_id' => $newModel->getKey(),
                            'changes' => [
                                '_created' => [
                                    'spcode' => $newModel->spcode,
                                    'chname_index' => $newModel->chname_index,
                                ]
                            ],
                            'modified_by' => $userCode,
                            'modified_at' => now(),
                        ]);
                    }
                }

            } else {
                // 先取得原資料
                $existing = collect($originalData)->firstWhere('id', $item['id']);
                $diff = [];
                // if (!$existing){
                //     $existing = $model::where('id', $item['id'])->get()->toArray();
                // }

                foreach ($data as $key => $value) {
                    if ($key === 'updated_at' || $key === 'created_at' || $key === 'created_by' || $key === 'updated_by' || $key === 'census_year') {
                        continue; // ✅ 排除 updated_at
                    }
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
