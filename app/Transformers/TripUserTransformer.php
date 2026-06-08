<?php

namespace STS\Transformers;

use League\Fractal\TransformerAbstract;
use STS\Models\User;

class TripUserTransformer extends TransformerAbstract
{
    protected $user;

    public function __construct($user)
    {
        $this->user = $user;
    }

    /**
     * Turn this item object into a generic array.
     */
    public function transformOrMissing(?User $user, ?int $userId = null): array
    {
        return $user ? $this->transform($user) : $this->missingUser($userId);
    }

    public function missingUser(?int $userId = null): array
    {
        return [
            'id' => $userId,
            'name' => 'Usuario inexistente',
            'descripcion' => '',
            'private_note' => '',
            'image' => '',
            'positive_ratings' => 0,
            'negative_ratings' => 0,
            'last_connection' => '',
            'accounts' => null,
            'has_pin' => false,
            'is_member' => false,
            'monthly_donate' => false,
            'do_not_alert_request_seat' => false,
            'do_not_alert_accept_passenger' => false,
            'do_not_alert_pending_rates' => false,
            'do_not_alert_pricing' => false,
            'autoaccept_requests' => false,
            'driver_is_verified' => false,
            'driver_data_docs' => null,
            'conversation_opened_count' => 0,
            'conversation_answered_count' => 0,
            'answer_delay_sum' => 0,
            'identity_validated_at' => null,
        ];
    }

    public function transform(User $user)
    {
        $data = [
            'id' => $user->id,
            'name' => $user->name,
            // 'email' => $user->email,
            'descripcion' => $user->description,
            'private_note' => $user->private_note,
            'image' => $user->image,
            'positive_ratings' => $user->positive_ratings,
            'negative_ratings' => $user->negative_ratings,
            'last_connection' => $user->last_connection->toDateTimeString(),
            'accounts' => $user->accounts,
            'has_pin' => $user->has_pin,
            'is_member' => $user->is_member,
            'monthly_donate' => $user->monthly_donate,
            'do_not_alert_request_seat' => $user->do_not_alert_request_seat,
            'do_not_alert_accept_passenger' => $user->do_not_alert_accept_passenger,
            'do_not_alert_pending_rates' => $user->do_not_alert_pending_rates,
            'do_not_alert_pricing' => $user->do_not_alert_pricing,
            'autoaccept_requests' => $user->autoaccept_requests,
            'driver_is_verified' => $user->driver_is_verified,
            'driver_data_docs' => $user->driver_data_docs ? json_decode($user->driver_data_docs) : null,
            'conversation_opened_count' => $user->conversation_opened_count,
            'conversation_answered_count' => $user->conversation_answered_count,
            'answer_delay_sum' => $user->answer_delay_sum,
            'identity_validated_at' => $user->identity_validated_at ? $user->identity_validated_at->toDateTimeString() : null,
        ];

        return $data;
    }
}
