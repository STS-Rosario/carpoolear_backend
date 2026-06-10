<?php

namespace Tests\Unit\Models;

use STS\Models\CarColor;
use Tests\TestCase;

class CarColorTest extends TestCase
{
    public function test_active_scope_orders_by_sort_order_then_name(): void
    {
        CarColor::factory()->create(['name' => 'Zul', 'sort_order' => 2, 'is_active' => true]);
        CarColor::factory()->create(['name' => 'Azul', 'sort_order' => 1, 'is_active' => true]);
        CarColor::factory()->create(['name' => 'Hidden', 'is_active' => false]);

        $names = CarColor::active()->pluck('name')->all();

        $this->assertSame(['Azul', 'Zul'], $names);
    }
}
