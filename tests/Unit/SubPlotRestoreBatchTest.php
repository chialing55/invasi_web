<?php

namespace Tests\Unit;

use App\Livewire\EntryEntry;
use App\Models\SubPlotEnv2025;
use App\Models\SubPlotPlant2025;
use App\Support\RecordStateGuard;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use ReflectionMethod;
use Tests\TestCase;

class SubPlotRestoreBatchTest extends TestCase
{
    private array $originalConnection;

    private const PLOT = '600001';

    private const PLOT_FULL_ID = '6000010801';

    private const UNDERSTORY_FULL_ID = '6000018801';

    private const BATCH = '6f1b2130-2244-4a6b-b0dc-529801617509';

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalConnection = config('database.connections.invasiflora');
        config()->set('database.connections.invasiflora', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        DB::purge('invasiflora');

        Schema::connection('invasiflora')->create('im_splotdata_2025', function (Blueprint $table) {
            $table->increments('id');
            $table->string('plot');
            $table->string('plot_full_id')->unique();
            $table->string('habitat_code')->nullable();
            $table->string('subplot_id')->nullable();
            $table->string('deleted_by')->nullable();
            $table->uuid('deletion_batch_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::connection('invasiflora')->create('im_spvptdata_2025', function (Blueprint $table) {
            $table->increments('id');
            $table->string('plot_full_id');
            $table->string('deleted_by')->nullable();
            $table->uuid('deletion_batch_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    protected function tearDown(): void
    {
        DB::purge('invasiflora');
        config()->set('database.connections.invasiflora', $this->originalConnection);

        parent::tearDown();
    }

    public function test_restore_only_recovers_plants_from_the_subplot_deletion_batch(): void
    {
        $this->insertEnvironment(null);
        DB::connection('invasiflora')->table('im_splotdata_2025')->update([
            'deleted_at' => null,
            'deleted_by' => null,
        ]);
        $sameBatchId = $this->insertPlant(null);
        DB::connection('invasiflora')->table('im_spvptdata_2025')
            ->where('id', $sameBatchId)
            ->update(['deleted_at' => null, 'deleted_by' => null]);
        $previouslyDeletedId = $this->insertPlant(null);

        $deleteComponent = $this->preparedDeleteComponent();
        $this->assertSame(1, $this->deleteBatch($deleteComponent));
        $deletedEnvironment = SubPlotEnv2025::onlyTrashed()->firstOrFail();
        $this->assertNotNull($deletedEnvironment->deletion_batch_id);
        $this->assertSame(
            $deletedEnvironment->deletion_batch_id,
            SubPlotPlant2025::onlyTrashed()->findOrFail($sameBatchId)->deletion_batch_id
        );
        $this->assertNull(SubPlotPlant2025::onlyTrashed()->findOrFail($previouslyDeletedId)->deletion_batch_id);

        $component = $this->preparedComponent();

        $restoredCount = $this->restoreBatch($component);

        $this->assertSame(1, $restoredCount);
        $this->assertNull(SubPlotEnv2025::withTrashed()->first()->deleted_at);
        $this->assertNull(SubPlotPlant2025::withTrashed()->find($sameBatchId)->deleted_at);
        $this->assertNull(SubPlotPlant2025::withTrashed()->find($sameBatchId)->deletion_batch_id);
        $this->assertNotNull(SubPlotPlant2025::withTrashed()->find($previouslyDeletedId)->deleted_at);
    }

    public function test_new_deletion_in_the_batch_invalidates_the_restore_prompt(): void
    {
        $this->insertEnvironment(self::BATCH);
        $this->insertPlant(self::BATCH);
        $component = $this->preparedComponent();
        $this->insertPlant(self::BATCH);

        $this->expectException(ValidationException::class);
        $this->restoreBatch($component);
    }

    public function test_old_deletion_without_batch_cannot_be_automatically_restored(): void
    {
        $this->insertEnvironment(null);
        $component = $this->preparedComponent();

        $this->expectException(ValidationException::class);
        $this->restoreBatch($component);
    }

    public function test_new_main_subplot_prompts_to_restore_trashed_paired_understory(): void
    {
        $this->insertEnvironment(self::BATCH, self::UNDERSTORY_FULL_ID);
        $this->insertPlant(self::BATCH, self::UNDERSTORY_FULL_ID);
        $component = new EntryEntry;
        $component->thisPlot = self::PLOT;

        $available = (new ReflectionMethod(EntryEntry::class, 'environmentTargetsAvailable'))
            ->invoke($component, [
                'plot' => self::PLOT,
                'plot_full_id' => self::PLOT_FULL_ID,
                'habitat_code' => '08',
                'subplot_id' => '01',
            ], null);

        $this->assertFalse($available);
        $this->assertSame(self::UNDERSTORY_FULL_ID, $component->pendingUnderstoryRestorePlotFullId);
        $this->assertNotSame('', $component->pendingUnderstoryRestoreToken);

        $understory = SubPlotEnv2025::onlyTrashed()->where('plot_full_id', self::UNDERSTORY_FULL_ID)->firstOrFail();
        $plants = SubPlotPlant2025::onlyTrashed()->where('plot_full_id', self::UNDERSTORY_FULL_ID)->get();
        $state = (new ReflectionMethod(EntryEntry::class, 'deletedSubPlotState'))
            ->invoke($component, $understory, $plants);
        $component->approvedUnderstoryRestoreToken = RecordStateGuard::token(
            'entry-understory-approval:'.self::PLOT.':'.self::UNDERSTORY_FULL_ID,
            $state
        );

        $availableAfterConfirmation = (new ReflectionMethod(EntryEntry::class, 'environmentTargetsAvailable'))
            ->invoke($component, [
                'plot' => self::PLOT,
                'plot_full_id' => self::PLOT_FULL_ID,
                'habitat_code' => '08',
                'subplot_id' => '01',
            ], null);

        $this->assertTrue($availableAfterConfirmation);
        $restoredCount = (new ReflectionMethod(EntryEntry::class, 'restorePendingUnderstoryBatch'))
            ->invoke($component, self::UNDERSTORY_FULL_ID);

        $this->assertSame(1, $restoredCount);
        $this->assertNull(SubPlotEnv2025::where('plot_full_id', self::UNDERSTORY_FULL_ID)->firstOrFail()->deleted_at);
        $this->assertNull(SubPlotPlant2025::where('plot_full_id', self::UNDERSTORY_FULL_ID)->firstOrFail()->deleted_at);
    }

    public function test_deleting_main_subplot_soft_deletes_paired_understory_in_same_batch(): void
    {
        $this->insertEnvironment(null);
        $this->insertEnvironment(null, self::UNDERSTORY_FULL_ID);
        $this->insertPlant(null);
        $this->insertPlant(null, self::UNDERSTORY_FULL_ID);
        DB::connection('invasiflora')->table('im_splotdata_2025')->update([
            'deleted_at' => null,
            'deleted_by' => null,
        ]);
        DB::connection('invasiflora')->table('im_spvptdata_2025')->update([
            'deleted_at' => null,
            'deleted_by' => null,
        ]);

        $component = $this->preparedDeleteComponent();
        $this->assertSame(2, $this->deleteBatch($component));

        $environments = SubPlotEnv2025::onlyTrashed()->orderBy('plot_full_id')->get();
        $plants = SubPlotPlant2025::onlyTrashed()->orderBy('plot_full_id')->get();
        $this->assertCount(2, $environments);
        $this->assertCount(2, $plants);
        $this->assertSame(1, $environments->pluck('deletion_batch_id')->unique()->count());
        $this->assertSame($environments->first()->deletion_batch_id, $plants->first()->deletion_batch_id);
    }

    public function test_changing_wood_subplot_to_unpaired_type_requires_pair_removal_confirmation(): void
    {
        $this->insertEnvironment(null);
        $this->insertEnvironment(null, self::UNDERSTORY_FULL_ID);
        $this->insertPlant(null, self::UNDERSTORY_FULL_ID);
        DB::connection('invasiflora')->table('im_splotdata_2025')->update([
            'deleted_at' => null,
            'deleted_by' => null,
        ]);
        DB::connection('invasiflora')->table('im_spvptdata_2025')->update([
            'deleted_at' => null,
            'deleted_by' => null,
        ]);
        $component = new EntryEntry;
        $component->thisPlot = self::PLOT;
        $main = SubPlotEnv2025::where('plot_full_id', self::PLOT_FULL_ID)->firstOrFail();

        $available = (new ReflectionMethod(EntryEntry::class, 'environmentTargetsAvailable'))
            ->invoke($component, [
                'plot' => self::PLOT,
                'plot_full_id' => self::PLOT.'0101',
                'habitat_code' => '01',
                'subplot_id' => '01',
            ], $main);

        $this->assertFalse($available);
        $this->assertSame(self::UNDERSTORY_FULL_ID, $component->pendingPairRemovalPlotFullId);
        $this->assertNotSame('', $component->pendingPairRemovalToken);
    }

    private function insertEnvironment(?string $batch, string $plotFullId = self::PLOT_FULL_ID): void
    {
        DB::connection('invasiflora')->table('im_splotdata_2025')->insert([
            'plot' => self::PLOT,
            'plot_full_id' => $plotFullId,
            'habitat_code' => substr($plotFullId, 6, 2),
            'subplot_id' => substr($plotFullId, 8, 2),
            'deleted_by' => 'tester',
            'deletion_batch_id' => $batch,
            'created_at' => '2026-09-25 09:00:00',
            'updated_at' => '2026-09-25 10:00:00',
            'deleted_at' => '2026-09-25 10:00:00',
        ]);
    }

    private function insertPlant(?string $batch, string $plotFullId = self::PLOT_FULL_ID): int
    {
        return DB::connection('invasiflora')->table('im_spvptdata_2025')->insertGetId([
            'plot_full_id' => $plotFullId,
            'deleted_by' => 'tester',
            'deletion_batch_id' => $batch,
            'created_at' => '2026-09-25 09:00:00',
            'updated_at' => '2026-09-25 10:00:00',
            'deleted_at' => '2026-09-25 10:00:00',
        ]);
    }

    private function preparedComponent(): EntryEntry
    {
        $component = new EntryEntry;
        $component->thisPlot = self::PLOT;
        $environment = SubPlotEnv2025::onlyTrashed()->firstOrFail();
        $plants = SubPlotPlant2025::onlyTrashed()
            ->where('deletion_batch_id', $environment->deletion_batch_id)
            ->get();
        $state = (new ReflectionMethod(EntryEntry::class, 'deletedSubPlotState'))
            ->invoke($component, $environment, $plants);
        $component->pendingRestoreToken = RecordStateGuard::token(
            'entry-restore:'.self::PLOT.':'.self::PLOT_FULL_ID,
            $state
        );

        return $component;
    }

    private function preparedDeleteComponent(): EntryEntry
    {
        $component = new EntryEntry;
        $component->thisPlot = self::PLOT;
        $component->thisSubPlot = self::PLOT_FULL_ID;
        $component->creatorCode = 'tester';
        $environment = SubPlotEnv2025::firstOrFail();
        $plant = SubPlotPlant2025::firstOrFail();
        $component->envRecordVersions = [(string) $environment->id => $environment->getRawOriginal('updated_at')];
        $component->plantRecordVersions = [(string) $plant->id => $plant->getRawOriginal('updated_at')];
        $tokenMethod = new ReflectionMethod(EntryEntry::class, 'recordVersionToken');
        $component->envVersionToken = $tokenMethod->invoke($component, 'environment', $component->envRecordVersions);
        $component->plantVersionToken = $tokenMethod->invoke($component, 'plants', $component->plantRecordVersions);

        return $component;
    }

    private function deleteBatch(EntryEntry $component): int
    {
        return (new ReflectionMethod(EntryEntry::class, 'deleteSubPlotBatch'))
            ->invoke($component, self::PLOT_FULL_ID);
    }

    private function restoreBatch(EntryEntry $component): int
    {
        return (new ReflectionMethod(EntryEntry::class, 'restoreDeletedSubPlotBatch'))
            ->invoke($component, self::PLOT_FULL_ID);
    }
}
