<?php

namespace App\Support;

final class UploadMime
{
    private const IMAGE_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public static function imageExtension(?string $mimeType): ?string
    {
        return self::IMAGE_EXTENSIONS[strtolower(trim((string) $mimeType))] ?? null;
    }

    public static function isPdf(?string $mimeType): bool
    {
        return strtolower(trim((string) $mimeType)) === 'application/pdf';
    }
}
