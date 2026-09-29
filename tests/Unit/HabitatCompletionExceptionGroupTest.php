<?php

namespace Tests\Unit;

use App\Livewire\EntryHabitatCompletionException;
use ReflectionMethod;
use Tests\TestCase;

class HabitatCompletionExceptionGroupTest extends TestCase
{
    public function test_wood_and_understory_are_an_authoritative_group(): void
    {
        $this->assertSame([
            '05' => ['05'],
            '08+88' => ['08', '88'],
            '09+99' => ['09', '99'],
        ], $this->canonicalGroups([88, '09', '05', '99', '08', '08']));
    }

    public function test_an_understory_without_its_wood_habitat_remains_a_single_group(): void
    {
        $this->assertSame([
            '06' => ['06'],
            '88' => ['88'],
        ], $this->canonicalGroups(['88', '06']));
    }

    private function canonicalGroups(array $codes): array
    {
        $method = new ReflectionMethod(EntryHabitatCompletionException::class, 'canonicalGroups');

        return $method->invoke(new EntryHabitatCompletionException, $codes);
    }
}
