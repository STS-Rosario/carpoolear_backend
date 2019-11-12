<?php

namespace STS\Transformers;

use STS\User;
use League\Fractal\TransformerAbstract;
use STS\Services\Logic\TripsManager as TripLogic;
use STS\Repository\TripRepository as TripRepo;

class ProfileTransformer extends TransformerAbstract
{
    protected $user;

    public function __construct($user)
    {
        $this->user = $user;
        $this->tripLogic = new TripLogic(new tripRepo);
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
            'description' => $user->description,
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
            'monthly_donate' => intval($user->monthly_donate),
            'autoaccept_requests'    => intval($user->autoaccept_requests),
            'driver_is_verified'    => intval($user->driver_is_verified),
            'driver_data_docs'      => $user->driver_data_docs ? json_decode($user->driver_data_docs) : null,
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
        }
        
        switch ($user->data_visibility) {
            case '1':
                # viaja conmigo
                if ($this->tripLogic->shareTrip($this->user, $user)) {
                    $data['nro_doc'] = $user->nro_doc;
                    $data['email'] = $user->email;
                    $data['mobile_phone'] = $user->mobile_phone;
                }
                break;
            case '2':
                # publico
                $data['nro_doc'] = $user->nro_doc;
                $data['email'] = $user->email;
                $data['mobile_phone'] = $user->mobile_phone;
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
