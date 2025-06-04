<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('organization')->nullable()->after('name');
            $table->string('role')->default('user')->after('email'); // 或改為 enum
            $table->string('title')->nullable()->after('organization'); // 👉 新增職稱欄位
            $table->string('google_id')->nullable()->unique()->after('password');
            $table->string('google_email')->nullable()->after('google_id');
            $table->string('google_avatar')->nullable()->after('google_email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['organization', 'role', 'title']);
        });
    }
};
