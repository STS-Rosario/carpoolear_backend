<?php

namespace STS\Transformers;

use STS\Models\User;
use League\Fractal\TransformerAbstract;
use STS\Repository\TripRepository;
use STS\Services\Logic\TripsManager;
use STS\Services\Logic\UsersManager;
use STS\Repository\UserRepository;
use STS\Helpers\IdentityValidationHelper;
use STS\Services\GeoService;
use STS\Services\MercadoPagoService;

class ProfileTransformer extends TransformerAbstract
{
    protected $user;
    protected $tripLogic;

    public function __construct($user)
    {
        $this->user = $user;
        $geoService = app(GeoService::class);
        $mercadoPagoService = app(MercadoPagoService::class);
        $tripRepository = new TripRepository($geoService, $mercadoPagoService);
        $this->tripLogic = new TripsManager($tripRepository, new UsersManager(new UserRepository, $tripRepository));
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
            'references_data' => $user->referencesReceived,
            // Identity validation: exposed to every user (all viewers of the profile)
            'identity_validated' => (bool) $user->identity_validated,
            'identity_validated_at' => $user->identity_validated_at ? $user->identity_validated_at->toDateTimeString() : null,
            'identity_validation_type' => $user->identity_validation_type,
        ];

        if ($this->user && $user->id == $this->user->id) {
            $data['identity_validation_required_for_user'] = IdentityValidationHelper::isNewUserRequiringValidation($user);
            $data['validate_by_date'] = $user->validate_by_date ? $user->validate_by_date->format('Y-m-d') : null;
        }
        if ($this->user && ($user->id == $this->user->id || $this->user->is_admin)) {
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
            
            // Always include car information for admins or the user themselves
            $data['cars'] = $user->cars;
            $data['patente'] = $user->cars->first() ? $user->cars->first()->patente : null;
            $data['car_description'] = $user->cars->first() ? $user->cars->first()->description : null;
        }
        
        switch ($user->data_visibility) {
            case '0':
                # viaja conmigo
                if ($this->user && $this->tripLogic->shareTrip($this->user, $user)) {
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
