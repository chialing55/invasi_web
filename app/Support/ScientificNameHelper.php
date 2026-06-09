<?php

declare(strict_types=1);

namespace App\Support;

final class ScientificNameHelper
{
    private const RANK_MARKERS = [
        'subsp.', 'ssp.', 'var.', 'subvar.', 'f.', 'forma',
        'cv.', '×', 'x',
    ];

    public static function italicize(?string $fullName, ?string $canonicalName = null): string
    {
        $fullName = trim((string) $fullName);
        $canonicalName = trim((string) $canonicalName);

        if ($fullName === '' && $canonicalName === '') {
            return '';
        }

        if ($fullName === '') {
            return self::canonicalToHtml($canonicalName);
        }

        if ($canonicalName === '') {
            return self::canonicalToHtml($fullName);
        }

        $pos = mb_strpos($fullName, $canonicalName);

        if ($pos === false) {
            return self::canonicalToHtml($fullName);
        }

        $before = mb_substr($fullName, 0, $pos);
        $after = mb_substr($fullName, $pos + mb_strlen($canonicalName));

        return self::authorshipToHtml($before) . self::canonicalToHtml($canonicalName) . self::authorshipToHtml($after);
    }

    public static function canonicalToHtml(?string $name): string
    {
        $name = trim((string) $name);

        if ($name === '') {
            return '';
        }

        $tokens = preg_split('/(\s+)/u', $name, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        $html = '';
        $italicBuffer = '';

        foreach ($tokens ?: [] as $token) {
            if (trim($token) === '') {
                $italicBuffer .= $token;
                continue;
            }

            if (self::isRankMarker($token)) {
                $html .= self::flushItalic($italicBuffer);
                $italicBuffer = '';
                $html .= e($token);
                continue;
            }

            $italicBuffer .= $token;
        }

        return $html . self::flushItalic($italicBuffer);
    }

    private static function isRankMarker(string $token): bool
    {
        return in_array(mb_strtolower(trim($token)), self::RANK_MARKERS, true);
    }

    private static function authorshipToHtml(string $text): string
    {
        if ($text === "") {
            return "";
        }

        $tokens = preg_split("/(\\s+)/u", $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        $html = "";

        foreach ($tokens ?: [] as $token) {
            $html .= mb_strtolower(trim($token)) === "ex"
                ? "<em>" . e($token) . "</em>"
                : e($token);
        }

        return $html;
    }

    private static function flushItalic(string $text): string
    {
        if ($text === '') {
            return '';
        }

        return '<em>' . e($text) . '</em>';
    }
}
