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
            'hab:05' => ['05'],
            'hab:08+88' => ['08', '88'],
            'hab:09+99' => ['09', '99'],
        ], $this->canonicalGroups([88, '09', '05', '99', '08', '08']));
    }

    public function test_an_understory_without_its_wood_habitat_remains_a_single_group(): void
    {
        $this->assertSame([
            'hab:06' => ['06'],
            'hab:88' => ['88'],
        ], $this->canonicalGroups(['88', '06']));
    }

    public function test_two_digit_numeric_code_uses_a_string_group_key(): void
    {
        $groups = $this->canonicalGroups(['10', 11]);

        $this->assertSame([
            'hab:10' => ['10'],
            'hab:11' => ['11'],
        ], $groups);
        $this->assertIsString(array_key_first($groups));
    }

    public function test_two_digit_habitat_rows_pass_the_authoritative_save_validation(): void
    {
        $component = new EntryHabitatCompletionException;
        $component->rows = [
            ['group_key' => 'hab:10', 'enabled' => true, 'actual_subplot_count' => '3'],
            ['group_key' => 'hab:11', 'enabled' => false, 'actual_subplot_count' => ''],
        ];
        $groups = $this->canonicalGroups(['10', '11']);
        $method = new ReflectionMethod($component, 'validatedSubmittedRows');

        $this->assertSame([
            'hab:10' => ['enabled' => true, 'actual_subplot_count' => 3],
            'hab:11' => ['enabled' => false, 'actual_subplot_count' => null],
        ], $method->invoke($component, $groups));
    }

    private function canonicalGroups(array $codes): array
    {
        $method = new ReflectionMethod(EntryHabitatCompletionException::class, 'canonicalGroups');

        return $method->invoke(new EntryHabitatCompletionException, $codes);
    }
}
