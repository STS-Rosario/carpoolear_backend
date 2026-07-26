<?php

namespace Tests\Unit\Services;

use Carbon\Carbon;
use STS\Events\Passenger\Accept;
use STS\Listeners\Conversation\addUserConversation;
use STS\Listeners\Conversation\createConversation;
use STS\Models\Conversation;
use STS\Models\Passenger;
use STS\Models\Trip;
use STS\Models\User;
use STS\Repository\ConversationRepository;
use STS\Services\Logic\ConversationsManager;
use STS\Services\TripGroupChatService;
use Tests\TestCase;

class TripGroupChatServiceTest extends TestCase
{
    private ConversationsManager $conversationManager;

    private ConversationRepository $conversationRepository;

    private TripGroupChatService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->conversationManager = $this->app->make(ConversationsManager::class);
        $this->conversationRepository = $this->app->make(ConversationRepository::class);
        $this->service = $this->app->make(TripGroupChatService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_trip_creation_does_not_create_group_chat(): void
    {
        $driver = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);

        $listener = new createConversation($this->conversationManager, $this->conversationRepository);
        $listener->handle(new \STS\Events\Trip\Create($trip));

        $this->assertNull($trip->fresh()->conversation);
    }

    public function test_group_chat_not_created_when_trip_has_fewer_than_three_participants(): void
    {
        $driver = User::factory()->create();
        $passenger = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);

        Passenger::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $passenger->id,
            'request_state' => Passenger::STATE_ACCEPTED,
        ]);

        $this->service->syncOnPassengerAccept($trip->fresh(), $passenger);

        $this->assertNull($trip->fresh()->conversation);
    }

    public function test_group_chat_created_with_all_participants_when_threshold_reached(): void
    {
        Carbon::setTestNow('2026-07-26 14:30:00');
        $driver = User::factory()->create();
        $passengerOne = User::factory()->create();
        $passengerTwo = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'to_town' => 'Mar del Plata',
            'trip_date' => Carbon::parse('2026-08-01 09:00:00'),
        ]);

        Passenger::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $passengerOne->id,
            'request_state' => Passenger::STATE_ACCEPTED,
        ]);
        Passenger::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $passengerTwo->id,
            'request_state' => Passenger::STATE_ACCEPTED,
        ]);

        $this->service->syncOnPassengerAccept($trip->fresh(), $passengerTwo);

        $conversation = $trip->fresh()->conversation;
        $this->assertInstanceOf(Conversation::class, $conversation);
        $this->assertSame(Conversation::TYPE_TRIP_CONVERSATION, (int) $conversation->type);
        $this->assertSame(3, $conversation->users()->count());
        $this->assertTrue($conversation->users()->whereKey($driver->id)->exists());
        $this->assertTrue($conversation->users()->whereKey($passengerOne->id)->exists());
        $this->assertTrue($conversation->users()->whereKey($passengerTwo->id)->exists());
    }

    public function test_add_user_listener_delegates_to_trip_group_chat_service(): void
    {
        $driver = User::factory()->create();
        $passengerOne = User::factory()->create();
        $passengerTwo = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);

        Passenger::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $passengerOne->id,
            'request_state' => Passenger::STATE_ACCEPTED,
        ]);
        Passenger::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $passengerTwo->id,
            'request_state' => Passenger::STATE_ACCEPTED,
        ]);

        $listener = new addUserConversation($this->conversationRepository, $this->conversationManager);
        $listener->handle(new Accept($trip, $driver, $passengerTwo));

        $conversation = $trip->fresh()->conversation;
        $this->assertNotNull($conversation);
        $this->assertSame(3, $conversation->users()->count());
    }
}
