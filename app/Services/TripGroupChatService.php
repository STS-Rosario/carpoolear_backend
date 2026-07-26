<?php

namespace STS\Services;

use STS\Models\Passenger;
use STS\Models\Trip;
use STS\Models\User;
use STS\Repository\ConversationRepository;
use STS\Services\Logic\ConversationsManager;

class TripGroupChatService
{
    public const MIN_PARTICIPANTS = 3;

    public function __construct(
        private ConversationsManager $conversationLogic,
        private ConversationRepository $conversationRepo,
    ) {}

    public function syncOnPassengerAccept(Trip $trip, User $acceptedUser): void
    {
        $trip = $trip->fresh();

        if ($this->participantCount($trip) < self::MIN_PARTICIPANTS) {
            return;
        }

        $conversation = $trip->conversation;
        $isNew = ! $conversation;

        if ($isNew) {
            $conversation = $this->conversationLogic->createTripConversation($trip->id);
            foreach ($this->participants($trip) as $participant) {
                $this->conversationRepo->addUser($conversation, $participant);
            }

            return;
        }

        if (! $conversation->users()->whereKey($acceptedUser->id)->exists()) {
            $this->conversationRepo->addUser($conversation, $acceptedUser);
            $this->conversationLogic->sendSystemMessage(
                $conversation->fresh(),
                $acceptedUser,
                'notifications.group_chat_user_joined',
                ['name' => $acceptedUser->name]
            );
        }
    }

    private function participantCount(Trip $trip): int
    {
        return 1 + $trip->passenger()
            ->whereIn('request_state', [
                Passenger::STATE_ACCEPTED,
                Passenger::STATE_WAITING_PAYMENT,
            ])
            ->count();
    }

    /**
     * @return \Illuminate\Support\Collection<int, User>
     */
    private function participants(Trip $trip)
    {
        $driver = User::find($trip->user_id);
        $passengers = $trip->passenger()
            ->whereIn('request_state', [
                Passenger::STATE_ACCEPTED,
                Passenger::STATE_WAITING_PAYMENT,
            ])
            ->with('user')
            ->get()
            ->map(fn (Passenger $passenger) => $passenger->user)
            ->filter();

        return collect([$driver])->merge($passengers)->filter()->unique('id');
    }
}
