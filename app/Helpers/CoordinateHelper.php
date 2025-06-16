<?php

namespace App\Helpers;

class CoordinateHelper
{
    public static function toDd97(float $x, float $y): array
    {
        $a = 6378137.0;
        $b = 6356752.314245;
        $lng0 = 121 * M_PI / 180;
        $k0 = 0.9999;
        $dx = 250000;

        $e = sqrt(1 - pow($b, 2) / pow($a, 2));
        $x -= $dx;
        $y /= $k0;

        $M = $y;
        $mu = $M / ($a * (1 - pow($e, 2) / 4 - 3 * pow($e, 4) / 64 - 5 * pow($e, 6) / 256));

        $e1 = (1 - sqrt(1 - pow($e, 2))) / (1 + sqrt(1 - pow($e, 2)));

        $J1 = (3 * $e1 / 2 - 27 * pow($e1, 3) / 32);
        $J2 = (21 * pow($e1, 2) / 16 - 55 * pow($e1, 4) / 32);
        $J3 = (151 * pow($e1, 3) / 96);
        $J4 = (1097 * pow($e1, 4) / 512);

        $fp = $mu + $J1 * sin(2 * $mu) + $J2 * sin(4 * $mu) + $J3 * sin(6 * $mu) + $J4 * sin(8 * $mu);

        $e2 = pow(($e * $a / $b), 2);
        $C1 = $e2 * pow(cos($fp), 2);
        $T1 = pow(tan($fp), 2);
        $R1 = $a * (1 - pow($e, 2)) / pow(1 - pow($e * sin($fp), 2), 1.5);
        $N1 = $a / sqrt(1 - pow($e * sin($fp), 2));

        $D = $x / ($N1 * $k0);

        $Q1 = $N1 * tan($fp) / $R1;
        $Q2 = pow($D, 2) / 2;
        $Q3 = (5 + 3 * $T1 + 10 * $C1 - 4 * pow($C1, 2) - 9 * $e2) * pow($D, 4) / 24;
        $Q4 = (61 + 90 * $T1 + 298 * $C1 + 45 * pow($T1, 2) - 3 * pow($C1, 2) - 252 * $e2) * pow($D, 6) / 720;
        $lat = $fp - $Q1 * ($Q2 - $Q3 + $Q4);

        $Q5 = $D;
        $Q6 = (1 + 2 * $T1 + $C1) * pow($D, 3) / 6;
        $Q7 = (5 - 2 * $C1 + 28 * $T1 - 3 * pow($C1, 2) + 8 * $e2 + 24 * pow($T1, 2)) * pow($D, 5) / 120;
        $lng = $lng0 + ($Q5 - $Q6 + $Q7) / cos($fp);

        return [
            'dd97_y' => round($lat * 180 / M_PI, 7),
            'dd97_x' => round($lng * 180 / M_PI, 7),
        ];
    }

    /**
     * 將經緯度 (dd97_x, dd97_y) 轉換為 TWD97 TM2 座標
     * 輸入單位：dd97_x = 經度、dd97_y = 緯度（十進制度）
     * 回傳 array: ['tm2_x' => ..., 'tm2_y' => ...]
     */
    public static function toTm2(float $lng, float $lat): array
    {
        // a, b 參數是 TWD97 的橢球參數（WGS84）
        $a = 6378137.0;
        $b = 6356752.314245;
        $lng0 = 121 * M_PI / 180; // 中央子午線（度轉弧度）
        $k0 = 0.9999;
        $dx = 250000;

        $lat = $lat * M_PI / 180; // 度→弧度
        $lng = $lng * M_PI / 180;

        $e = sqrt(1 - pow($b, 2) / pow($a, 2));
        $e2 = pow($e, 2) / (1 - pow($e, 2));
        $n = ($a - $b) / ($a + $b);
        $nu = $a / sqrt(1 - pow($e, 2) * pow(sin($lat), 2));
        $p = $lng - $lng0;

        $A = $a * (1 - pow($e, 2) / 4 - 3 * pow($e, 4) / 64 - 5 * pow($e, 6) / 256);

        $B = 3 * $a * pow($e, 2) / 8 + 3 * $a * pow($e, 4) / 32 + 45 * $a * pow($e, 6) / 1024;
        $C = 15 * $a * pow($e, 4) / 256 + 45 * $a * pow($e, 6) / 1024;
        $D = 35 * $a * pow($e, 6) / 3072;

        $S = $A * $lat - $B * sin(2 * $lat) + $C * sin(4 * $lat) - $D * sin(6 * $lat);

        $K1 = $S * $k0;
        $K2 = $k0 * $nu * sin(2 * $lat) / 4;
        $K3 = (5 - tan($lat) * tan($lat) + 9 * $e2 * pow(cos($lat), 2) + 4 * pow($e2, 2) * pow(cos($lat), 4)) * $k0 * $nu * pow(sin($lat), 4) / 24;

        $y = $K1 + $K2 * pow($p, 2) + $K3 * pow($p, 4);

        $K4 = $k0 * $nu * cos($lat);
        $K5 = (1 - tan($lat) * tan($lat) + $e2 * pow(cos($lat), 2)) * $k0 * $nu * pow(cos($lat), 3) / 6;

        $x = $K4 * $p + $K5 * pow($p, 3) + $dx;

        return [
            'tm2_x' => round($x), // TWD97 X
            'tm2_y' => round($y), // TWD97 Y
        ];
    }

}
