<?php

namespace Tests\Feature\Http;

use Carbon\Carbon;
use STS\Http\Middleware\UserAdmin;
use STS\Models\AdminActionLog;
use STS\Models\Passenger;
use STS\Models\Rating;
use STS\Models\Trip;
use STS\Models\User;
use Tests\TestCase;

class AdminRatingControllerIntegrationTest extends TestCase
{
    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->saveQuietly();

        return $user->fresh();
    }

    private function persistRating(array $attributes): Rating
    {
        $rating = new Rating;
        foreach ($attributes as $key => $value) {
            $rating->{$key} = $value;
        }
        if (! array_key_exists('available', $attributes)) {
            $rating->available = 0;
        }
        $rating->save();

        return $rating->fresh();
    }

    public function test_update_requires_admin(): void
    {
        $user = User::factory()->create();
        $rated = User::factory()->create();
        $voter = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $voter->id]);
        $rating = $this->persistRating([
            'trip_id' => $trip->id,
            'user_id_from' => $voter->id,
            'user_id_to' => $rated->id,
            'user_to_type' => Passenger::TYPE_PASAJERO,
            'user_to_state' => Passenger::STATE_ACCEPTED,
            'rating' => Rating::STATE_POSITIVO,
            'comment' => 'Original',
            'reply_comment' => '',
            'voted' => true,
            'voted_hash' => '',
            'rate_at' => Carbon::now(),
            'available' => 1,
        ]);

        $this->actingAs($user, 'api');

        $this->patchJson('api/admin/ratings/'.$rating->id, [
            'rating' => Rating::STATE_NEGATIVO,
            'comment' => 'Updated',
        ])->assertUnauthorized();
    }

    public function test_admin_update_persists_fields_and_logs_action(): void
    {
        $admin = $this->admin();
        $rated = User::factory()->create();
        $voter = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $voter->id]);
        $rating = $this->persistRating([
            'trip_id' => $trip->id,
            'user_id_from' => $voter->id,
            'user_id_to' => $rated->id,
            'user_to_type' => Passenger::TYPE_PASAJERO,
            'user_to_state' => Passenger::STATE_ACCEPTED,
            'rating' => Rating::STATE_POSITIVO,
            'comment' => 'Original comment',
            'reply_comment' => 'Original reply',
            'voted' => true,
            'voted_hash' => '',
            'rate_at' => Carbon::now(),
            'available' => 1,
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->patchJson('api/admin/ratings/'.$rating->id, [
            'rating' => Rating::STATE_NEGATIVO,
            'comment' => 'Updated comment',
            'reply_comment' => 'Updated reply',
        ])
            ->assertOk()
            ->assertJsonPath('data.rating', Rating::STATE_NEGATIVO)
            ->assertJsonPath('data.comment', 'Updated comment')
            ->assertJsonPath('data.reply_comment', 'Updated reply');

        $this->assertDatabaseHas('rating', [
            'id' => $rating->id,
            'rating' => Rating::STATE_NEGATIVO,
            'comment' => 'Updated comment',
            'reply_comment' => 'Updated reply',
        ]);

        $log = AdminActionLog::query()
            ->where('admin_user_id', $admin->id)
            ->where('action', AdminActionLog::ACTION_RATING_UPDATE)
            ->where('target_user_id', $rated->id)
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('rating', $log->details['entity_type']);
        $this->assertSame($rating->id, $log->details['entity_id']);
        $this->assertSame(Rating::STATE_POSITIVO, $log->details['before']['rating']);
        $this->assertSame('Original comment', $log->details['before']['comment']);
        $this->assertSame(Rating::STATE_NEGATIVO, $log->details['after']['rating']);
        $this->assertSame('Updated comment', $log->details['after']['comment']);
    }

    public function test_update_returns_unprocessable_for_invalid_rating_value(): void
    {
        $admin = $this->admin();
        $rated = User::factory()->create();
        $voter = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $voter->id]);
        $rating = $this->persistRating([
            'trip_id' => $trip->id,
            'user_id_from' => $voter->id,
            'user_id_to' => $rated->id,
            'user_to_type' => Passenger::TYPE_PASAJERO,
            'user_to_state' => Passenger::STATE_ACCEPTED,
            'rating' => Rating::STATE_POSITIVO,
            'comment' => 'Original',
            'reply_comment' => '',
            'voted' => true,
            'voted_hash' => '',
            'rate_at' => Carbon::now(),
            'available' => 1,
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->patchJson('api/admin/ratings/'.$rating->id, [
            'rating' => 99,
        ])->assertUnprocessable();
    }

    public function test_update_returns_not_found_for_missing_rating(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->patchJson('api/admin/ratings/999999999', [
            'comment' => 'Nope',
        ])->assertNotFound();
    }
}
