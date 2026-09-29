<?php

namespace Tests\Unit;

use App\Support\UploadMime;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UploadMimeTest extends TestCase
{
    public static function supportedImages(): array
    {
        return [
            'jpeg' => ['image/jpeg', 'jpg'],
            'png' => ['image/png', 'png'],
            'webp' => ['image/webp', 'webp'],
            'normalized input' => [' IMAGE/JPEG ', 'jpg'],
        ];
    }

    #[DataProvider('supportedImages')]
    public function test_image_extension_comes_from_detected_mime(string $mime, string $extension): void
    {
        $this->assertSame($extension, UploadMime::imageExtension($mime));
    }

    public function test_unsupported_or_missing_image_mime_is_rejected(): void
    {
        $this->assertNull(UploadMime::imageExtension('image/gif'));
        $this->assertNull(UploadMime::imageExtension('application/x-php'));
        $this->assertNull(UploadMime::imageExtension(null));
    }

    public function test_forged_original_extension_is_not_used(): void
    {
        $image = UploadedFile::fake()->image('payload.jpg');
        $upload = new UploadedFile(
            $image->getPathname(),
            'payload.php',
            null,
            null,
            true
        );

        $this->assertSame('php', $upload->getClientOriginalExtension());
        $this->assertSame('jpg', UploadMime::imageExtension($upload->getMimeType()));
    }

    public function test_only_pdf_mime_is_accepted_for_pdf_uploads(): void
    {
        $this->assertTrue(UploadMime::isPdf('application/pdf'));
        $this->assertTrue(UploadMime::isPdf(' APPLICATION/PDF '));
        $this->assertFalse(UploadMime::isPdf('text/plain'));
        $this->assertFalse(UploadMime::isPdf(null));
    }
}
