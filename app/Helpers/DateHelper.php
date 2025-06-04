<?php

namespace App\Helpers;

class DateHelper
{
    /**
     * 拆解 Y-m-d 成 year, month, day（皆為 int）
     */
    public static function splitYmd(string $date): array
    {
        $ts = strtotime($date);

        return [
            'year' => (int) date('Y', $ts),
            'month' => (int) date('m', $ts),
            'day' => (int) date('d', $ts),
        ];
    }
}
