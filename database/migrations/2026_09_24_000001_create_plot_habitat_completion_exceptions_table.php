<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('invasiflora')->hasTable('plot_habitat_completion_exceptions')) {
            return;
        }

        Schema::connection('invasiflora')->create('plot_habitat_completion_exceptions', function (Blueprint $table) {
            $table->id();
            $table->string('team', 50);
            $table->string('county', 50);
            $table->string('plot', 30);
            $table->char('habitat_code', 2);
            $table->unsignedTinyInteger('actual_subplot_count');
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();
            $table->timestamps();

            $table->unique(
                ['plot', 'habitat_code'],
                'plot_hab_completion_plot_hab_unique'
            );
            $table->index('team', 'plot_hab_completion_team_index');
            $table->index('county', 'plot_hab_completion_county_index');
        });
    }

    public function down(): void
    {
        Schema::connection('invasiflora')->dropIfExists('plot_habitat_completion_exceptions');
    }
};
