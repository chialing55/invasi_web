<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = ['im_splotdata_2025', 'im_spvptdata_2025'];

    public function up(): void
    {
        $schema = Schema::connection('invasiflora');

        foreach (self::TABLES as $tableName) {
            if (! $schema->hasColumn($tableName, 'deletion_batch_id')) {
                $schema->table($tableName, function (Blueprint $table) {
                    $table->uuid('deletion_batch_id')->nullable()->after('deleted_by');
                });
            }
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('invasiflora');

        foreach (self::TABLES as $tableName) {
            if ($schema->hasColumn($tableName, 'deletion_batch_id')) {
                $schema->table($tableName, function (Blueprint $table) {
                    $table->dropColumn('deletion_batch_id');
                });
            }
        }
    }
};
