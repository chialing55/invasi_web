<?php

namespace App\Services;

use App\Models\FixLog;

class FixLogService
{
    /**
     * 紀錄模型的欄位異動資訊
     *
     * @param \Illuminate\Database\Eloquent\Model $model    要比對的舊資料模型實體
     * @param array $newData                                新資料陣列（通常是表單）
     * @param string $userCode                              修改者代號（如 email 前綴）
     */
    public function log($model, array $newData, string $userCode): void
    {
        $changes = [];

        foreach ($newData as $field => $newValue) {
            // 排除系統時間欄位
            if (in_array($field, ['created_at', 'updated_at'])) {
                continue;
            }

            $oldValue = $model->$field ?? null;

            if ((string) $oldValue !== (string) $newValue) {
                $changes[$field] = [
                    'old' => $oldValue,
                    'new' => $newValue,
                ];
            }
        }

        if (!empty($changes)) {
            FixLog::create([
                'table_name' => $model->getTable(),
                'record_id' => $model->getKey(),
                'changes' => $changes, // ⚠ 直接存 array，model casts JSON
                'modified_by' => $userCode,
                'modified_at' => now(),
            ]);
        }
    }

}
