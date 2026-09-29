<?php

namespace Tests\Unit;

use App\Livewire\QueryPlant;
use PHPUnit\Framework\TestCase;

class QueryPlantFilterTest extends TestCase
{
    public function test_county_and_habitat_filters_are_combined(): void
    {
        $component = $this->componentWithRows();

        $component->reloadPlantInfoCounty('臺北市');
        $component->reloadPlantInfoHab('08');

        $this->assertCount(1, $component->filteredComparisonTable);
        $this->assertSame('臺北市', $component->filteredComparisonTable[0]['county']);
        $this->assertSame('08', $component->filteredComparisonTable[0]['hab_code']);
        $this->assertSame(['08' => '天然林', '09' => '人工林'], $component->habList);
    }

    public function test_all_only_clears_the_changed_filter(): void
    {
        $component = $this->componentWithRows();
        $component->reloadPlantInfoCounty('臺北市');
        $component->reloadPlantInfoHab('08');

        $component->reloadPlantInfoCounty('');

        $this->assertCount(2, $component->filteredComparisonTable);
        $this->assertSame(['08'], collect($component->filteredComparisonTable)->pluck('hab_code')->unique()->values()->all());
        $this->assertSame($component->allHabList, $component->habList);
    }

    public function test_county_only_lists_habitats_where_the_plant_was_recorded(): void
    {
        $component = $this->componentWithRows();

        $component->reloadPlantInfoCounty('臺中市');

        $this->assertSame(['08' => '天然林'], $component->habList);
        $this->assertCount(1, $component->filteredComparisonTable);
    }

    public function test_county_outside_the_current_habitat_options_is_rejected(): void
    {
        $component = $this->componentWithRows();
        $component->reloadPlantInfoCounty('臺北市');
        $component->reloadPlantInfoHab('09');

        $component->reloadPlantInfoCounty('臺中市');

        $this->assertSame('', $component->thisCounty);
        $this->assertSame('09', $component->thisHabType);
        $this->assertSame($component->allHabList, $component->habList);
        $this->assertCount(1, $component->filteredComparisonTable);
    }

    public function test_habitat_only_lists_counties_where_the_plant_was_recorded(): void
    {
        $component = $this->componentWithRows();

        $component->reloadPlantInfoHab('09');

        $this->assertSame(['臺北市'], $component->countyList);
        $this->assertCount(1, $component->filteredComparisonTable);
    }

    public function test_clearing_habitat_restores_all_counties(): void
    {
        $component = $this->componentWithRows();
        $component->reloadPlantInfoHab('09');

        $component->reloadPlantInfoHab('');

        $this->assertSame($component->allCountyList, $component->countyList);
        $this->assertCount(3, $component->filteredComparisonTable);
    }

    private function componentWithRows(): QueryPlant
    {
        $component = new QueryPlant;
        $component->allCountyList = ['臺中市', '臺北市'];
        $component->countyList = $component->allCountyList;
        $component->allHabList = ['08' => '天然林', '09' => '人工林'];
        $component->habList = $component->allHabList;
        $component->comparisonTable = [
            ['county' => '臺北市', 'hab_code' => '08', 'habitat' => '天然林'],
            ['county' => '臺北市', 'hab_code' => '09', 'habitat' => '人工林'],
            ['county' => '臺中市', 'hab_code' => '08', 'habitat' => '天然林'],
        ];

        return $component;
    }
}
