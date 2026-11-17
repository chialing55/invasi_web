<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('status', 20)->default('active')->index()->after('role');
            $table->timestamp('approved_at')->nullable()->after('status');
            $table->unsignedBigInteger('approved_by')->nullable()->index()->after('approved_at');
            $table->text('rejected_reason')->nullable()->after('approved_by');
            $table->boolean('must_change_password')->default(false)->after('password');
            $table->timestamp('temporary_password_expires_at')->nullable()->after('must_change_password');
        });

        // Historical email verification is the migration boundary between
        // already accepted accounts and applications still awaiting review.
        DB::table('users')
            ->whereNull('deleted_at')
            ->whereNotNull('email_verified_at')
            ->update([
                'status' => 'active',
                'approved_at' => DB::raw('COALESCE(email_verified_at, CURRENT_TIMESTAMP)'),
            ]);

        DB::table('users')
            ->whereNull('deleted_at')
            ->whereNull('email_verified_at')
            ->where('role', '!=', 'admin')
            ->update([
                'status' => 'pending',
                'approved_at' => null,
                'approved_by' => null,
            ]);

        // Never lock out the existing administrators during this transition,
        // even if an old administrator record has no verification timestamp.
        DB::table('users')
            ->whereNull('deleted_at')
            ->where('role', 'admin')
            ->update([
                'status' => 'active',
                'approved_at' => now(),
            ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['approved_by']);
            $table->dropColumn([
                'status', 'approved_at', 'approved_by', 'rejected_reason',
                'must_change_password', 'temporary_password_expires_at',
            ]);
        });
    }
};
