<?php

namespace Tests\Unit\Helpers;

use STS\Helpers\SelladoTripCount;
use STS\Models\Trip;
use STS\Models\User;
use Tests\TestCase;

class SelladoTripCountTest extends TestCase
{
    public function test_for_user_can_exclude_the_trip_being_created_or_updated(): void
    {
        $user = User::factory()->create();
        $prior = Trip::factory()->create(['user_id' => $user->id]);
        $current = Trip::factory()->create(['user_id' => $user->id]);

        $this->assertSame(2, SelladoTripCount::forUser($user->id));
        $this->assertSame(1, SelladoTripCount::forUser($user->id, $current->id));
        $this->assertSame(1, SelladoTripCount::forUser($user->id, $prior->id));
    }
}
