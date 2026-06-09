<?php

namespace App\Support;

class FamilyChartImage
{
    public static function title(array $selectedPlots): string
    {
        return self::countyLabel($selectedPlots) . '地區歸化物種優勢科前十名排名圖';
    }

    public static function render(array $rows, string $path, string $title = ''): void
    {
        $rows = array_values(array_filter($rows, fn($row) => (int) (($row['物種數'] ?? 0)) > 0));
        if (empty($rows)) {
            $rows = [['植物科名' => '無資料', '物種數' => 0]];
        }

        $width = 980;
        $height = 590;
        $left = 105;
        $right = 70;
        $top = 55;
        $bottom = 165;
        $plotW = $width - $left - $right;
        $plotH = $height - $top - $bottom;

        $img = imagecreatetruecolor($width, $height);
        imageantialias($img, true);
        $white = imagecolorallocate($img, 255, 255, 255);
        $black = imagecolorallocate($img, 0, 0, 0);
        $gray = imagecolorallocate($img, 90, 90, 90);
        $border = imagecolorallocate($img, 210, 210, 210);
        imagefill($img, 0, 0, $white);
        imagerectangle($img, 8, 8, $width - 9, $height - 9, $border);

        $font = self::fontPath(false);
        $boldFont = self::fontPath(true);

        $maxValue = max(array_map(fn($row) => (int) $row['物種數'], $rows));
        $yMax = max(5, (int) ceil($maxValue / 5) * 5);
        if ($yMax === $maxValue) {
            $yMax += 5;
        }

        imageline($img, $left, $top, $left, $top + $plotH, $black);
        imageline($img, $left, $top + $plotH, $left + $plotW, $top + $plotH, $black);

        for ($tick = 0; $tick <= $yMax; $tick += 5) {
            $y = (int) round($top + $plotH - ($tick / $yMax) * $plotH);
            imageline($img, $left - 8, $y, $left, $y, $black);
            self::text($img, (string) $tick, 14, 0, $left - 42, $y + 5, $black, $font, 'left');
        }

        self::verticalText($img, '物種數', 17, $left - 70, $top + (int) ($plotH / 2) + 35, $black, $font);
        self::text($img, '科別', 17, 0, $left + (int) ($plotW / 2), $height - 38, $black, $font, 'center');

        $count = count($rows);
        $slot = $plotW / max(1, $count);
        $barW = min(28, max(18, (int) floor($slot * 0.28)));

        foreach ($rows as $i => $row) {
            $name = (string) ($row['植物科名'] ?? '');
            $value = (int) ($row['物種數'] ?? 0);
            $cx = (int) round($left + $slot * ($i + 0.5));
            $barH = (int) round(($value / $yMax) * $plotH);
            $x1 = $cx - (int) floor($barW / 2);
            $x2 = $x1 + $barW;
            $y1 = $top + $plotH - $barH;
            $y2 = $top + $plotH;

            imagefilledrectangle($img, $x1, $y1, $x2, $y2, $gray);
            self::text($img, (string) $value, 14, 0, $cx, $y1 - 8, $black, $font, 'center');
            imageline($img, $cx, $y2, $cx, $y2 + 7, $black);
            self::verticalText($img, $name, 15, $cx - 9, $y2 + 30, $black, $font);
        }

        if ($title !== '') {
            self::text($img, $title, 16, 0, $width / 2, 34, $black, $boldFont, 'center');
        }

        imagepng($img, $path);
        imagedestroy($img);
    }

    private static function countyLabel(array $selectedPlots): string
    {
        $plots = array_values(array_filter($selectedPlots, fn($plot) => $plot !== null && $plot !== ''));
        if (empty($plots)) {
            return '選取縣市';
        }

        $counties = \App\Models\PlotList2025::query()
            ->whereIn('plot', $plots)
            ->whereNotNull('county')
            ->distinct()
            ->orderBy('county')
            ->pluck('county')
            ->filter()
            ->values()
            ->all();

        if (count($counties) === 1) {
            return (string) $counties[0];
        }

        return count($counties) > 1 ? implode('、', $counties) : '選取縣市';
    }

    private static function fontPath(bool $bold): string
    {
        $base = storage_path('app/fonts');
        $candidates = $bold
            ? [$base . '/NotoSerifCJK-Regular.ttc', $base . '/NotoSansCJK-Regular.ttc', '/usr/share/fonts/opentype/noto/NotoSerifCJK-Bold.ttc', '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf']
            : [$base . '/NotoSerifCJK-Regular.ttc', $base . '/NotoSansCJK-Regular.ttc', '/usr/share/fonts/opentype/noto/NotoSerifCJK-Regular.ttc', '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf'];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';
    }

    private static function text($img, string $text, int $size, int $angle, int|float $x, int|float $y, int $color, string $font, string $align = 'left'): void
    {
        $box = imagettfbbox($size, $angle, $font, $text);
        $w = abs(($box[2] ?? 0) - ($box[0] ?? 0));
        if ($align === 'center') {
            $x -= $w / 2;
        } elseif ($align === 'right') {
            $x -= $w;
        }
        imagettftext($img, $size, $angle, (int) round($x), (int) round($y), $color, $font, $text);
    }

    private static function verticalText($img, string $text, int $size, int $x, int $baselineY, int $color, string $font): void
    {
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $lineH = $size + 8;
        $startY = $baselineY - (int) floor((count($chars) - 1) * $lineH / 2);
        foreach ($chars as $i => $char) {
            self::text($img, $char, $size, 0, $x, $startY + $i * $lineH, $color, $font, 'center');
        }
    }
}
