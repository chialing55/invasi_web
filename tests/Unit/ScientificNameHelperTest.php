<?php

namespace Tests\Unit;

use App\Support\ScientificNameHelper;
use PHPUnit\Framework\TestCase;

class ScientificNameHelperTest extends TestCase
{
    public function test_italicizes_simple_canonical_name(): void
    {
        $this->assertSame(
            '<em>Psilotum nudum</em>',
            ScientificNameHelper::italicize('Psilotum nudum', 'Psilotum nudum')
        );
    }

    public function test_keeps_author_outside_italic_when_full_name_contains_canonical_name(): void
    {
        $this->assertSame(
            '<em>Psilotum nudum</em> (L.) P.Beauv.',
            ScientificNameHelper::italicize('Psilotum nudum (L.) P.Beauv.', 'Psilotum nudum')
        );
    }

    public function test_keeps_rank_markers_upright(): void
    {
        $this->assertSame(
            '<em>Acer albopurpurascens </em>var.<em> albopurpurascens</em> Hayata',
            ScientificNameHelper::italicize(
                'Acer albopurpurascens var. albopurpurascens Hayata',
                'Acer albopurpurascens var. albopurpurascens'
            )
        );
    }

    public function test_keeps_subspecies_marker_upright(): void
    {
        $this->assertSame(
            '<em>Example plant </em>subsp.<em> taiwanensis</em> Author',
            ScientificNameHelper::italicize(
                'Example plant subsp. taiwanensis Author',
                'Example plant subsp. taiwanensis'
            )
        );
    }
    public function test_italicizes_ex_in_authorship(): void
    {
        $this->assertSame(
            '<em>Example plant</em> Author1 <em>ex</em> Author2',
            ScientificNameHelper::italicize(
                'Example plant Author1 ex Author2',
                'Example plant'
            )
        );
    }
}
