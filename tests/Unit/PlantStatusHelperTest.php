<?php

namespace Tests\Unit;

use App\Support\PlantStatusHelper;
use PHPUnit\Framework\TestCase;

class PlantStatusHelperTest extends TestCase
{
    public function test_labels_include_endemic_and_native(): void
    {
        $this->assertSame(['特有', '原生'], PlantStatusHelper::labels('native', 1));
    }

    public function test_naturalized_origin_status_displays_as_alien(): void
    {
        $this->assertSame(['外來'], PlantStatusHelper::labels('naturalized', 0));
    }

    public function test_cultivated_origin_status_displays_as_cultivated(): void
    {
        $this->assertSame(['栽培'], PlantStatusHelper::labels('cultivated', 0));
    }

    public function test_blank_origin_status_displays_as_unknown(): void
    {
        $this->assertSame(['不明'], PlantStatusHelper::labels('', 0));
    }
}
