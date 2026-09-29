<?php

namespace Tests\Unit;

use App\Livewire\EntryHabitatCompletionException;
use App\Models\PlotHab;
use App\Models\PlotHabitatCompletionException;
use ReflectionMethod;
use Tests\TestCase;

class HabitatCompletionStateTokenTest extends TestCase
{
    public function test_same_state_produces_same_signed_token(): void
    {
        $component = new EntryHabitatCompletionException;
        $habitats = collect([$this->habitat(1, '08', '2026-09-25 10:00:00')]);
        $exceptions = collect([$this->exception(2, '08', 3, '2026-09-25 10:00:00')]);

        $this->assertSame(
            $this->token($component, $habitats, $exceptions),
            $this->token($component, $habitats, $exceptions)
        );
    }

    public function test_habitat_or_exception_changes_produce_a_different_token(): void
    {
        $component = new EntryHabitatCompletionException;
        $original = $this->token(
            $component,
            collect([$this->habitat(1, '08', '2026-09-25 10:00:00')]),
            collect([$this->exception(2, '08', 3, '2026-09-25 10:00:00')])
        );

        $changedHabitat = $this->token(
            $component,
            collect([$this->habitat(1, '09', '2026-09-25 10:00:00')]),
            collect([$this->exception(2, '08', 3, '2026-09-25 10:00:00')])
        );
        $changedException = $this->token(
            $component,
            collect([$this->habitat(1, '08', '2026-09-25 10:00:00')]),
            collect([$this->exception(2, '08', 4, '2026-09-25 10:00:00')])
        );

        $this->assertNotSame($original, $changedHabitat);
        $this->assertNotSame($original, $changedException);
    }

    private function token(EntryHabitatCompletionException $component, $habitats, $exceptions): string
    {
        $method = new ReflectionMethod($component, 'completionStateToken');

        return $method->invoke($component, $habitats, $exceptions);
    }

    private function habitat(int $id, string $code, string $updatedAt): PlotHab
    {
        $model = new PlotHab;
        $model->setRawAttributes([
            'id' => $id,
            'habitat_code' => $code,
            'updated_at' => $updatedAt,
        ], true);

        return $model;
    }

    private function exception(int $id, string $code, int $count, string $updatedAt): PlotHabitatCompletionException
    {
        $model = new PlotHabitatCompletionException;
        $model->setRawAttributes([
            'id' => $id,
            'habitat_code' => $code,
            'actual_subplot_count' => $count,
            'updated_at' => $updatedAt,
        ], true);

        return $model;
    }
}
