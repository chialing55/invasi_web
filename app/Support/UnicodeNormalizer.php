<?php

namespace App\Support;

use Normalizer;

class UnicodeNormalizer
{
    public static function nfkc(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        $normalized = Normalizer::normalize($value, Normalizer::FORM_KC);

        return $normalized === false ? $value : $normalized;
    }
}
