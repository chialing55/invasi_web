<?php

namespace Tests\Unit;

use App\Support\UnicodeNormalizer;
use PHPUnit\Framework\TestCase;

class UnicodeNormalizerTest extends TestCase
{
    public function test_it_normalizes_cjk_compatibility_characters(): void
    {
        $this->assertSame('類雛菊飛蓬', UnicodeNormalizer::nfkc('類雛菊飛蓬'));
        $this->assertSame('阿里山脈葉蘭', UnicodeNormalizer::nfkc('阿里山脈葉蘭'));
    }

    public function test_it_preserves_standard_text_and_null(): void
    {
        $this->assertSame('類燕麥翦股穎', UnicodeNormalizer::nfkc('類燕麥翦股穎'));
        $this->assertNull(UnicodeNormalizer::nfkc(null));
    }
}
