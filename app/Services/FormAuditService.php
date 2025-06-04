<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;

class FormAuditService
{
    /**
     * 根據是否已存在資料，自動設定 created_by 或 updated_by。
     *
     * @param  class-string<Model>  $modelClass
     * @param  array  $where 條件陣列（如 ['plot_full_id' => 'XXX']）
     * @param  array  &$formData 要寫入的表單陣列（會直接修改）
     * @param  string  $userCode 使用者識別代碼（email 前綴、id 等）
     * @return string 回傳是 'created' 或 'updated'
     */
    public function attachAuditFields(string $modelClass, array $where, array &$formData, string $userCode): string
    {
        $existing = $modelClass::where($where)->exists();

        if ($existing) {
            $formData['updated_by'] = $userCode;
            unset($formData['created_by']);
            return 'updated';
        } else {
            $formData['created_by'] = $userCode;
            unset($formData['updated_by']);
            return 'created';
        }
    }
}
