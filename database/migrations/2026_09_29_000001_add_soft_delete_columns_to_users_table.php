<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'deleted_by')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedBigInteger('deleted_by')->nullable()->index();
            });
        }

        if (! Schema::hasColumn('users', 'deleted_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'deleted_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }

        if (Schema::hasColumn('users', 'deleted_by')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropIndex(['deleted_by']);
                $table->dropColumn('deleted_by');
            });
        }
    }
};
