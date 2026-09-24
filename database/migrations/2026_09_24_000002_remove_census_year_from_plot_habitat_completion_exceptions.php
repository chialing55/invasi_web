<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::connection('invasiflora')->hasColumn('plot_habitat_completion_exceptions', 'census_year')) {
            return;
        }

        Schema::connection('invasiflora')->table('plot_habitat_completion_exceptions', function (Blueprint $table) {
            $table->dropUnique('plot_hab_completion_year_plot_hab_unique');
            $table->dropIndex('plot_hab_completion_year_team_index');
            $table->dropIndex('plot_hab_completion_year_county_index');
            $table->dropColumn('census_year');
        });

        Schema::connection('invasiflora')->table('plot_habitat_completion_exceptions', function (Blueprint $table) {
            $table->unique(['plot', 'habitat_code'], 'plot_hab_completion_plot_hab_unique');
            $table->index('team', 'plot_hab_completion_team_index');
            $table->index('county', 'plot_hab_completion_county_index');
        });
    }

    public function down(): void
    {
        Schema::connection('invasiflora')->table('plot_habitat_completion_exceptions', function (Blueprint $table) {
            $table->dropUnique('plot_hab_completion_plot_hab_unique');
            $table->dropIndex('plot_hab_completion_team_index');
            $table->dropIndex('plot_hab_completion_county_index');
            $table->unsignedSmallInteger('census_year')->nullable()->after('id');
        });

        Schema::connection('invasiflora')->table('plot_habitat_completion_exceptions', function (Blueprint $table) {
            $table->unique(
                ['census_year', 'plot', 'habitat_code'],
                'plot_hab_completion_year_plot_hab_unique'
            );
            $table->index(['census_year', 'team'], 'plot_hab_completion_year_team_index');
            $table->index(['census_year', 'county'], 'plot_hab_completion_year_county_index');
        });
    }
};
