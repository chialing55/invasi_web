<?php

namespace Tests\Unit;

use App\Livewire\EntryMissingnote;
use Illuminate\Validation\ValidationException;
use ReflectionMethod;
use Tests\TestCase;

class MissingReasonValidationTest extends TestCase
{
    public function test_blank_or_active_reason_code_is_accepted(): void
    {
        $this->validateRows([
            ['not_done_reason_code' => '', 'description' => ''],
            ['not_done_reason_code' => '5-2-1', 'description' => str_repeat('字', 500)],
        ], ['1', '5-2-1']);

        $this->addToAssertionCount(1);
    }

    public function test_unknown_or_inactive_reason_code_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        $this->validateRows([
            ['not_done_reason_code' => 'inactive', 'description' => ''],
        ], ['1', '5-2-1']);
    }

    public function test_description_longer_than_500_characters_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        $this->validateRows([
            ['not_done_reason_code' => '1', 'description' => str_repeat('字', 501)],
        ], ['1']);
    }

    public function test_nested_reason_value_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        $this->validateRows([
            ['not_done_reason_code' => ['1'], 'description' => ''],
        ], ['1']);
    }

    private function validateRows(array $rows, array $activeCodes): void
    {
        $method = new ReflectionMethod(EntryMissingnote::class, 'validateReasonForm');
        $method->invoke(new EntryMissingnote, $rows, $activeCodes);
    }
}
