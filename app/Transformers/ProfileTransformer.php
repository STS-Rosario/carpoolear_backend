<?php

namespace STS\Transformers;

use STS\Models\User;
use League\Fractal\TransformerAbstract;
use STS\Repository\TripRepository;
use STS\Services\Logic\TripsManager;
use STS\Services\Logic\UsersManager;
use STS\Repository\UserRepository;

class ProfileTransformer extends TransformerAbstract
{
    protected $user;
    protected $tripLogic;

    public function __construct($user)
    {
        $this->user = $user;
        $this->tripLogic = new TripsManager(new TripRepository, new UsersManager(new UserRepository, new TripRepository));
    }

    /**
     * Turn this item object into a generic array.
     *
     * @return array
     */
    public function transform(User $user)
    {
        $data = [
            'id' => $user->id,
            'name' => $user->name,
            // 'email' => $user->email,
            'badges' => $user->badges,
            'description' => $user->description,
            'private_note' => $user->private_note,
            'image' => $user->image,
            'positive_ratings' => $user->positive_ratings,
            'negative_ratings' => $user->negative_ratings,
            'birthday' => $user->birthday,
            'gender' => $user->gender,
            // 'mobile_phone' => $user->mobile_phone,
            // 'nro_doc' => $user->nro_doc,
            'last_connection' => $user->last_connection ? $user->last_connection->toDateTimeString() : '',
            'accounts' => $user->accounts,
            'donations' => $user->donations,
            'has_pin' => intval($user->has_pin),
            'is_member' => intval($user->is_member),
            'banned' => intval($user->banned),
            'active' => intval($user->active),
            'monthly_donate' => $user->monthly_donate,
            'do_not_alert_request_seat'       => intval($user->do_not_alert_request_seat),
            'do_not_alert_accept_passenger'   => intval($user->do_not_alert_accept_passenger),
            'do_not_alert_pending_rates'      => intval($user->do_not_alert_pending_rates),
            'do_not_alert_pricing'      => intval($user->do_not_alert_pricing),
            'monthly_donate' => intval($user->monthly_donate),
            'unaswered_messages_limit'    => intval($user->unaswered_messages_limit),
            'autoaccept_requests'    => intval($user->autoaccept_requests),
            'driver_is_verified'    => intval($user->driver_is_verified),
            'driver_data_docs'      => $user->driver_data_docs ? json_decode($user->driver_data_docs) : null,
            'references' => $user->references,
            'data_visibility' => $user->data_visibility,
            'references_data' => $user->referencesReceived
        ];

        if ($user->id == $this->user->id || $this->user->is_admin) {
            $data['emails_notifications'] = $user->emails_notifications;
            $data['is_admin'] = $user->is_admin;
            $data['accounts'] = $user->accounts;
            $data['donations'] = $user->donations;
            $data['email'] = $user->email;
            $data['mobile_phone'] = $user->mobile_phone;
            $data['nro_doc'] = $user->nro_doc;
            // bank data
            $data['account_number'] = $user->account_number;
            $data['account_type'] = $user->account_type;
            $data['account_bank'] = $user->account_bank;
            $data['on_boarding_view'] = $user->on_boarding_view;
        }
        
        switch ($user->data_visibility) {
            case '0':
                # viaja conmigo
                if ($this->tripLogic->shareTrip($this->user, $user)) {
                    $data['nro_doc'] = $user->nro_doc;
                    $data['email'] = $user->email;
                    $data['mobile_phone'] = $user->mobile_phone;
                    $data['cars'] = $user->cars;
                }
                break;
            case '1':
                # publico
                $data['nro_doc'] = $user->nro_doc;
                $data['email'] = $user->email;
                $data['mobile_phone'] = $user->mobile_phone;
                $data['cars'] = $user->cars;
                break;
            default:
                # privado
                break;
        }

        if ($user->state) {
            $data['state'] = $user->state;
        }

        return $data;
    }
}
