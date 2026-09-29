<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'im_splotdata_2025';

    private const UNIQUE_INDEX = 'im_splotdata_2025_plot_full_id_unique';

    public function up(): void
    {
        $schema = Schema::connection('invasiflora');
        if (! $schema->hasTable(self::TABLE) || ! $schema->hasColumn(self::TABLE, 'plot_full_id')) {
            return;
        }

        $indexes = collect($schema->getIndexes(self::TABLE));
        if ($indexes->contains(fn (array $index) => $index['name'] === self::UNIQUE_INDEX)) {
            return;
        }

        if ($indexes->contains(fn (array $index) => $index['name'] === 'plot_full_id' && ! $index['unique'])) {
            $schema->table(self::TABLE, function (Blueprint $table) {
                $table->dropIndex('plot_full_id');
            });
        }

        $schema->table(self::TABLE, function (Blueprint $table) {
            $table->unique('plot_full_id', self::UNIQUE_INDEX);
        });
    }

    public function down(): void
    {
        $schema = Schema::connection('invasiflora');
        if (! $schema->hasTable(self::TABLE) || ! $schema->hasColumn(self::TABLE, 'plot_full_id')) {
            return;
        }

        $indexes = collect($schema->getIndexes(self::TABLE));
        if ($indexes->contains(fn (array $index) => $index['name'] === self::UNIQUE_INDEX)) {
            $schema->table(self::TABLE, function (Blueprint $table) {
                $table->dropUnique(self::UNIQUE_INDEX);
            });
        }

        $indexes = collect($schema->getIndexes(self::TABLE));
        if (! $indexes->contains(fn (array $index) => $index['name'] === 'plot_full_id')) {
            $schema->table(self::TABLE, function (Blueprint $table) {
                $table->index('plot_full_id', 'plot_full_id');
            });
        }
    }
};
