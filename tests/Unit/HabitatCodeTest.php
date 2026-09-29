<?php

namespace Tests\Unit;

use App\Support\HabitatCode;
use PHPUnit\Framework\TestCase;

class HabitatCodeTest extends TestCase
{
    public function test_pairs_and_classifications_come_from_one_mapping(): void
    {
        $this->assertSame('88', HabitatCode::understoryFor('08'));
        $this->assertSame('99', HabitatCode::understoryFor('09'));
        $this->assertSame('77', HabitatCode::understoryFor('19'));
        $this->assertSame('19', HabitatCode::mainFor('77'));
        $this->assertTrue(HabitatCode::isWood('19'));
        $this->assertTrue(HabitatCode::isUnderstory('77'));
        $this->assertNotContains('19', HabitatCode::herbMainCodes());
        $this->assertNotContains('77', HabitatCode::herbMainCodes());
    }

    public function test_selected_main_codes_control_their_derived_codes(): void
    {
        $this->assertSame(
            ['01', '19', '77'],
            HabitatCode::syncSelectedCodes(['01', '19', '88'])
        );
    }

    public function test_legacy_pairs_do_not_include_new_2025_habitats(): void
    {
        $this->assertSame(['08' => '88', '09' => '99'], HabitatCode::legacyPairs());
        $this->assertArrayNotHasKey('19', HabitatCode::legacyPairs());
    }

    public function test_normalized_sql_merges_every_understory_into_its_main_habitat(): void
    {
        $sql = HabitatCode::normalizedSql('e.habitat_code');

        $this->assertStringContainsString("'88', 88) THEN '08'", $sql);
        $this->assertStringContainsString("'99', 99) THEN '09'", $sql);
        $this->assertStringContainsString("'77', 77) THEN '19'", $sql);
    }

    public function test_selected_codes_are_canonicalized_deduplicated_and_linked(): void
    {
        $this->assertSame(
            ['01', '08', '88'],
            HabitatCode::normalizeSelectedCodes([1, '08', '08', '88'])
        );
        $this->assertSame([], HabitatCode::normalizeSelectedCodes(['88']));
    }

    public function test_unknown_or_malformed_selected_codes_are_rejected(): void
    {
        foreach (['55', 'abc', '../08'] as $invalid) {
            try {
                HabitatCode::normalizeSelectedCodes([$invalid]);
                $this->fail("Expected {$invalid} to be rejected.");
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->expectException(\InvalidArgumentException::class);
        HabitatCode::normalizeSelectedCodes([['08']]);
    }
}
