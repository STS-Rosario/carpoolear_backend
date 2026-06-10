<?php

namespace STS\Transformers;

use League\Fractal\TransformerAbstract;
use STS\Models\Trip;
use STS\Services\Logic\FriendsManager;

class TripTransformer extends TransformerAbstract
{
    protected $user;

    public function __construct($user)
    {
        $this->user = $user;
    }

    /**
     * Turn this item object into a generic array.
     *
     * @return array
     */
    public function transform(Trip $trip)
    {
        $data = [
            'id' => $trip->id,
            'from_town' => $trip->from_town,
            'to_town' => $trip->to_town,
            'trip_date' => $trip->trip_date ? $trip->trip_date->toDateTimeString() : null,
            'weekly_schedule' => $trip->weekly_schedule,
            'weekly_schedule_time' => $trip->weekly_schedule_time,
            'description' => $trip->description,
            'total_seats' => $trip->total_seats,
            'friendship_type_id' => $trip->friendship_type_id,
            'distance' => $trip->distance,
            'estimated_time' => $trip->estimated_time,
            'seat_price_cents' => $trip->seat_price_cents,
            'recommended_trip_price_cents' => $trip->recommended_trip_price_cents,
            'total_price' => $trip->total_price,
            'state' => $trip->state,
            'is_passenger' => $trip->is_passenger,
            'passenger_count' => $trip->passenger_count,
            'seats_available' => $trip->seats_available,
            'points' => $trip->points,
            'ratings' => $trip->ratings,
            'updated_at' => $trip->updated_at->toDateTimeString(),
            'allow_kids' => $trip->allow_kids,
            'allow_animals' => $trip->allow_animals,
            'allow_smoking' => $trip->allow_smoking,
            'rear_max_two_passengers' => $trip->rear_max_two_passengers,
            'payment_id' => $trip->payment_id,
            'needs_sellado' => $trip->needs_sellado,
        ];

        // Flag for frontend: show faded (e.g. 80% opacity) and "Falta pagar Sellado" when sellado is unpaid
        $data['sellado_pending'] = ($trip->needs_sellado && $trip->state !== Trip::STATE_READY) ? true : false;
        $data['sellado_pending_label'] = $data['sellado_pending'] ? 'Falta pagar Sellado' : null;

        if ($trip->deleted_at) {
            $data['deleted_at'] = $trip->deleted_at->toDateTimeString();
            if ($trip->deleted_at->toDateTimeString() === '2000-01-01 00:00:00') {
                $data['hidden'] = true;
            } else {
                $data['deleted'] = true;
            }
        }

        $data['request'] = '';
        $data['passenger'] = [];
        if ($this->user) {
            $friendsManager = app(FriendsManager::class);
            $data['driver_is_friend'] = $friendsManager->areFriend($this->user, $trip->user);

            $userTranforms = new TripUserTransformer($this->user);
            $data['user'] = $userTranforms->transform($trip->user);
            if ($trip->isPassenger($this->user) || $trip->user_id == $this->user->id || $this->user->is_admin) {
                $data['allPassengerRequest'] = $trip->passenger;
                if ($trip->isPassenger($this->user) || $trip->user_id == $this->user->id) {
                    foreach ($trip->passengerAccepted as $passenger) {
                        $data['passenger'][] = $userTranforms->transform($passenger->user);
                    }
                    $data['car'] = $this->transformCar($trip->car);
                } elseif ($trip->isPending($this->user)) {
                    $data['request'] = 'send';
                }

                $data['car'] = $this->transformCar($trip->car);
                $data['request_count'] = count($trip->passenger);
                $data['passengerAccepted_count'] = count($trip->passengerAccepted);
                foreach ($trip->passenger as $prequest) {
                    $prequest->request_id = $prequest->id;
                    $prequest->id = $prequest->user->id;
                    $prequest->name = $prequest->user->name;
                    $prequest->email = $prequest->user->email;
                }
            } elseif ($trip->isPending($this->user)) {
                $data['request'] = 'send';
            }
            // passengerPending
            $data['passengerPending_count'] = count($trip->passengerPending);

            $data['group_chat_conversation_id'] = null;
            $data['group_chat_unread_count'] = 0;
            if ($trip->canAccessGroupChat($this->user)) {
                $conversationManager = app(\STS\Services\Logic\ConversationsManager::class);
                $groupConversation = $conversationManager->getConversationByTrip($this->user, $trip->id);
                if ($groupConversation) {
                    $messageRepo = app(\STS\Repository\MessageRepository::class);
                    $data['group_chat_conversation_id'] = $groupConversation->id;
                    $data['group_chat_unread_count'] = $messageRepo
                        ->getUnreadMessages($groupConversation, $this->user)
                        ->count();
                }
            }

        }

        return $data;
    }

    private function transformCar($car): ?array
    {
        if (! $car) {
            return null;
        }

        $car->loadMissing(['brand', 'carModel', 'color']);
        $payload = $car->toArray();
        if (method_exists($car, 'trashed') && $car->trashed()) {
            $payload['deleted'] = true;
        }

        return $payload;
    }
}
