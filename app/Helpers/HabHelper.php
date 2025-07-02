<?php
// app/Helpers/HabHelper.php

namespace App\Helpers;

use App\Models\HabitatInfo;

class HabHelper
{
    /**
     * 根據樣區代碼清單回傳 habitat code + label 的對照表
     *
     * @param array $plotHabList
     * @return array
     */
    public static function habitatOptions(array $plotHabList): array
    {
        $habTypeMap = HabitatInfo::pluck('habitat', 'habitat_code')->toArray();

        return collect($plotHabList)
            ->filter(fn($code) => isset($habTypeMap[$code]))
            ->mapWithKeys(fn($code) => [$code => $habTypeMap[$code]])
            // ->map(fn($label, $code) => $code . ' ' . $label)
            ->sortKeys()
            ->toArray();
    }
}
