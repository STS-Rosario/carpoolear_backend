<?php

namespace Tests\Unit\Transformers;

use Carbon\Carbon;
use Illuminate\Support\Arr;
use STS\Models\Conversation;
use STS\Models\Message;
use STS\Models\Rating;
use STS\Models\Trip;
use STS\Models\User;
use STS\Transformers\ConversationsTransformer;
use Tests\TestCase;

class ConversationsTransformerTest extends TestCase
{
    public function test_transform_private_conversation_includes_core_fields_last_message_and_users(): void
    {
        $viewer = User::factory()->create();
        $other = User::factory()->create([
            'name' => 'Other User',
            'image' => 'other.png',
            'identity_validated_at' => Carbon::parse('2026-04-29 10:00:00'),
            'last_connection' => Carbon::parse('2026-04-30 09:00:00'),
        ]);
        $conversation = Conversation::query()->create([
            'type' => Conversation::TYPE_PRIVATE_CONVERSATION,
            'title' => 'Private chat',
        ]);
        $conversation->users()->attach($viewer->id, ['read' => false]);
        $conversation->users()->attach($other->id, ['read' => true]);

        $message = Message::query()->create([
            'user_id' => $other->id,
            'conversation_id' => $conversation->id,
            'text' => 'Latest text',
            'estado' => Message::STATE_NOLEIDO,
        ]);
        $message->forceFill(['created_at' => Carbon::parse('2026-04-30 12:00:00')])->saveQuietly();

        $payload = (new ConversationsTransformer($viewer))->transform($conversation->fresh());

        $this->assertSame($conversation->id, $payload['id']);
        $this->assertSame(Conversation::TYPE_PRIVATE_CONVERSATION, $payload['type']);
        $this->assertSame('Other User', $payload['title']);
        $this->assertSame('/image/profile/other.png', $payload['image']);
        $this->assertSame('2026-04-29 10:00:00', $payload['other_user_identity_validated_at']);
        $this->assertTrue($payload['unread']);
        $this->assertNotNull($payload['update_at']);
        $this->assertSame('Latest text', $payload['last_message']['text']);
        $this->assertCount(2, $payload['users']);
        $this->assertSame($viewer->id, $payload['users'][0]['id']);
    }

    public function test_transform_uses_unknown_user_fallback_when_private_peer_is_missing(): void
    {
        $viewer = User::factory()->create();
        $conversation = Conversation::query()->create([
            'type' => Conversation::TYPE_PRIVATE_CONVERSATION,
            'title' => 'Orphan chat',
        ]);
        $conversation->users()->attach($viewer->id, ['read' => true]);

        $payload = (new ConversationsTransformer($viewer))->transform($conversation->fresh());

        $this->assertSame('Unknown User', $payload['title']);
        $this->assertSame('', $payload['image']);
        $this->assertNull($payload['other_user_identity_validated_at']);
        $this->assertFalse($payload['unread']);
    }

    public function test_transform_includes_trip_and_return_trip_when_coordinate_module_enabled(): void
    {
        config(['carpoolear.module_coordinate_by_message' => true]);

        $viewer = User::factory()->create(['is_admin' => true]);
        $owner = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $owner->id,
            'state' => Trip::STATE_READY,
            'needs_sellado' => false,
        ]);
        $returnTrip = Trip::factory()->create([
            'user_id' => $owner->id,
            'state' => Trip::STATE_READY,
            'needs_sellado' => false,
        ]);
        $trip->return_trip_id = $returnTrip->id;
        $trip->save();

        $conversation = Conversation::query()->create([
            'type' => Conversation::TYPE_TRIP_CONVERSATION,
            'title' => 'Trip chat',
            'trip_id' => $trip->id,
        ]);
        $conversation->users()->attach($viewer->id, ['read' => true]);

        $payload = (new ConversationsTransformer($viewer))->transform($conversation->fresh());

        $this->assertArrayHasKey('trip', $payload);
        $this->assertArrayHasKey('return_trip', $payload);
        $this->assertSame($trip->id, $payload['trip']['id']);
        $this->assertSame($returnTrip->id, $payload['return_trip']['id']);
        $this->assertSame('Trip chat', $payload['title']);
    }

    public function test_transform_skips_trip_payload_when_coordinate_module_disabled_even_with_trip_id(): void
    {
        config(['carpoolear.module_coordinate_by_message' => false]);

        $viewer = User::factory()->create();
        $owner = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $owner->id]);

        $conversation = Conversation::query()->create([
            'type' => Conversation::TYPE_TRIP_CONVERSATION,
            'title' => 'Trip chat',
            'trip_id' => $trip->id,
        ]);
        $conversation->users()->attach($viewer->id, ['read' => true]);

        $payload = (new ConversationsTransformer($viewer))->transform($conversation->fresh());

        $this->assertArrayNotHasKey('trip', $payload);
        $this->assertArrayNotHasKey('return_trip', $payload);
    }

    public function test_transform_skips_coordinate_branch_when_module_key_missing_from_config(): void
    {
        $originalCarpoolear = config('carpoolear');
        try {
            config(['carpoolear' => Arr::except($originalCarpoolear, ['module_coordinate_by_message'])]);

            $viewer = User::factory()->create();
            $owner = User::factory()->create();
            $trip = Trip::factory()->create(['user_id' => $owner->id]);

            $conversation = Conversation::query()->create([
                'type' => Conversation::TYPE_TRIP_CONVERSATION,
                'title' => 'Trip chat',
                'trip_id' => $trip->id,
            ]);
            $conversation->users()->attach($viewer->id, ['read' => true]);

            $payload = (new ConversationsTransformer($viewer))->transform($conversation->fresh());

            $this->assertArrayNotHasKey('trip', $payload);
            $this->assertArrayNotHasKey('return_trip', $payload);
        } finally {
            config(['carpoolear' => $originalCarpoolear]);
        }
    }

    public function test_transform_users_rows_each_expose_stable_key_order(): void
    {
        $viewer = User::factory()->create(['name' => 'Viewer']);
        $other = User::factory()->create(['name' => 'Other']);
        $conversation = Conversation::query()->create([
            'type' => Conversation::TYPE_PRIVATE_CONVERSATION,
            'title' => 'Chat',
        ]);
        $conversation->users()->attach($viewer->id, ['read' => true]);
        $conversation->users()->attach($other->id, ['read' => true]);

        $payload = (new ConversationsTransformer($viewer))->transform($conversation->fresh());

        $expectedKeys = [
            'id',
            'name',
            'last_connection',
            'identity_validated_at',
            'positive_ratings',
            'negative_ratings',
            'neutral_ratings',
        ];
        foreach ($payload['users'] as $row) {
            $this->assertSame($expectedKeys, array_keys($row));
        }
    }

    public function test_transform_private_peer_without_image_uses_empty_string_not_null(): void
    {
        $viewer = User::factory()->create();
        $other = User::factory()->create([
            'name' => 'No Image Peer',
            'image' => null,
        ]);
        $conversation = Conversation::query()->create([
            'type' => Conversation::TYPE_PRIVATE_CONVERSATION,
            'title' => 'Private',
        ]);
        $conversation->users()->attach($viewer->id, ['read' => true]);
        $conversation->users()->attach($other->id, ['read' => true]);

        $payload = (new ConversationsTransformer($viewer))->transform($conversation->fresh());

        $this->assertSame('', $payload['image']);
    }

    public function test_transform_default_branch_keeps_conversation_title_for_non_private_types(): void
    {
        $viewer = User::factory()->create();
        $conversation = Conversation::query()->create([
            'type' => Conversation::TYPE_TRIP_CONVERSATION,
            'title' => 'Trip room title',
        ]);
        $conversation->users()->attach($viewer->id, ['read' => true]);

        $payload = (new ConversationsTransformer($viewer))->transform($conversation->fresh());

        $this->assertSame('Trip room title', $payload['title']);
        $this->assertSame('', $payload['image']);
        $this->assertNull($payload['other_user_identity_validated_at']);
    }

    public function test_transform_users_include_positive_and_negative_ratings(): void
    {
        $viewer = User::factory()->create();
        $other = User::factory()->create(['name' => 'Rated User']);
        $raterOne = User::factory()->create();
        $raterTwo = User::factory()->create();
        $raterThree = User::factory()->create();
        $tripOne = Trip::factory()->create(['user_id' => $other->id]);
        $tripTwo = Trip::factory()->create(['user_id' => $other->id]);
        $tripThree = Trip::factory()->create(['user_id' => $other->id]);

        Rating::factory()->create([
            'trip_id' => $tripOne->id,
            'user_id_to' => $other->id,
            'user_id_from' => $raterOne->id,
            'rating' => Rating::STATE_POSITIVO,
            'available' => 1,
        ]);
        Rating::factory()->create([
            'trip_id' => $tripTwo->id,
            'user_id_to' => $other->id,
            'user_id_from' => $raterTwo->id,
            'rating' => Rating::STATE_POSITIVO,
            'available' => 1,
        ]);
        Rating::factory()->create([
            'trip_id' => $tripThree->id,
            'user_id_to' => $other->id,
            'user_id_from' => $raterThree->id,
            'rating' => Rating::STATE_NEGATIVO,
            'available' => 1,
        ]);

        $conversation = Conversation::query()->create([
            'type' => Conversation::TYPE_PRIVATE_CONVERSATION,
            'title' => 'Chat',
        ]);
        $conversation->users()->attach($viewer->id, ['read' => true]);
        $conversation->users()->attach($other->id, ['read' => true]);

        $payload = (new ConversationsTransformer($viewer))->transform($conversation->fresh());

        $otherRow = collect($payload['users'])->firstWhere('id', $other->id);
        $this->assertNotNull($otherRow);
        $this->assertSame(2, $otherRow['positive_ratings']);
        $this->assertSame(1, $otherRow['negative_ratings']);
    }

    public function test_transform_users_list_includes_identity_and_last_connection_fields(): void
    {
        $viewer = User::factory()->create([
            'last_connection' => Carbon::parse('2026-04-30 08:00:00'),
            'identity_validated_at' => Carbon::parse('2026-04-29 09:00:00'),
        ]);
        $conversation = Conversation::query()->create([
            'type' => Conversation::TYPE_PRIVATE_CONVERSATION,
            'title' => 'Chat',
        ]);
        $conversation->users()->attach($viewer->id, ['read' => true]);

        $payload = (new ConversationsTransformer($viewer))->transform($conversation->fresh());

        $viewerRow = collect($payload['users'])->firstWhere('id', $viewer->id);
        $this->assertNotNull($viewerRow);
        $this->assertSame('2026-04-30 08:00:00', $viewerRow['last_connection']);
        $this->assertSame('2026-04-29 09:00:00', $viewerRow['identity_validated_at']);
    }
}
