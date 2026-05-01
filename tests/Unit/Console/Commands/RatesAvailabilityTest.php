<?php

namespace Tests\Unit\Console\Commands;

use Carbon\Carbon;
use STS\Console\Commands\RatesAvailability;
use STS\Models\Rating;
use STS\Models\Trip;
use STS\Models\User;
use Tests\TestCase;

class RatesAvailabilityTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_handle_marks_recent_reciprocal_ratings_as_available_and_keeps_unpaired_unavailable(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 28, 10, 0, 0));

        $u1 = User::factory()->create();
        $u2 = User::factory()->create();
        $u3 = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $u1->id]);

        Rating::query()->create([
            'trip_id' => $trip->id,
            'user_id_from' => $u1->id,
            'user_id_to' => $u2->id,
            'user_to_type' => 1,
            'user_to_state' => 1,
            'rating' => Rating::STATE_POSITIVO,
            'comment' => 'old voted',
            'reply_comment' => '',
            'reply_comment_created_at' => null,
            'voted' => true,
            'rate_at' => Carbon::now()->subDays(30),
            'voted_hash' => 'hash-old',
            'available' => false,
            'created_at' => Carbon::now()->subDays(30),
            'updated_at' => Carbon::now()->subDays(30),
        ]);

        $recentA = Rating::query()->create([
            'trip_id' => $trip->id,
            'user_id_from' => $u1->id,
            'user_id_to' => $u3->id,
            'user_to_type' => 1,
            'user_to_state' => 1,
            'rating' => Rating::STATE_POSITIVO,
            'comment' => 'recent A',
            'reply_comment' => '',
            'reply_comment_created_at' => null,
            'voted' => true,
            'rate_at' => Carbon::now()->subDays(2),
            'voted_hash' => 'hash-a',
            'available' => false,
            'created_at' => Carbon::now()->subDays(2),
            'updated_at' => Carbon::now()->subDays(2),
        ]);
        $recentB = Rating::query()->create([
            'trip_id' => $trip->id,
            'user_id_from' => $u3->id,
            'user_id_to' => $u1->id,
            'user_to_type' => 0,
            'user_to_state' => 1,
            'rating' => Rating::STATE_NEGATIVO,
            'comment' => 'recent B',
            'reply_comment' => '',
            'reply_comment_created_at' => null,
            'voted' => true,
            'rate_at' => Carbon::now()->subDays(2),
            'voted_hash' => 'hash-b',
            'available' => false,
            'created_at' => Carbon::now()->subDays(2),
            'updated_at' => Carbon::now()->subDays(2),
        ]);

        $recentUnpaired = Rating::query()->create([
            'trip_id' => $trip->id,
            'user_id_from' => $u2->id,
            'user_id_to' => $u3->id,
            'user_to_type' => 1,
            'user_to_state' => 1,
            'rating' => Rating::STATE_POSITIVO,
            'comment' => 'unpaired',
            'reply_comment' => '',
            'reply_comment_created_at' => null,
            'voted' => true,
            'rate_at' => Carbon::now()->subDays(2),
            'voted_hash' => 'hash-c',
            'available' => false,
            'created_at' => Carbon::now()->subDays(2),
            'updated_at' => Carbon::now()->subDays(2),
        ]);

        $this->artisan('rating:availables')->assertExitCode(0);

        $this->assertTrue((bool) $recentA->fresh()->available);
        $this->assertTrue((bool) $recentB->fresh()->available);
        $this->assertFalse((bool) $recentUnpaired->fresh()->available);
    }

    public function test_command_contract_is_defined(): void
    {
        $command = new RatesAvailability;

        $this->assertSame('rating:availables', $command->getName());
        $this->assertStringContainsString('Makes rates available', $command->getDescription());
    }
}
