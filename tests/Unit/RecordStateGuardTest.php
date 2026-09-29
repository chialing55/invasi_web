<?php

namespace Tests\Unit;

use App\Support\RecordStateGuard;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RecordStateGuardTest extends TestCase
{
    public function test_token_is_bound_to_its_plot_scope(): void
    {
        $state = ['rows' => [['id' => '1', 'updated_at' => '2026-09-25 10:00:00']]];
        $token = RecordStateGuard::token('missing-reasons:600001', $state);

        $this->expectException(ValidationException::class);

        RecordStateGuard::assertToken(
            $token,
            'missing-reasons:600002',
            $state,
            'reasonForm',
            '資料已變更。'
        );
    }

    public function test_changed_row_values_invalidate_token_even_when_timestamp_is_unchanged(): void
    {
        $token = RecordStateGuard::token('missing-reasons:600001', [
            'rows' => [['id' => '1', 'description' => '原資料']],
        ]);

        $this->expectException(ValidationException::class);

        RecordStateGuard::assertToken(
            $token,
            'missing-reasons:600001',
            ['rows' => [['id' => '1', 'description' => '新資料']]],
            'reasonForm',
            '資料已變更。'
        );
    }
}
