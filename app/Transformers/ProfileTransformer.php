<?php

namespace STS\Transformers;

use Carbon\Carbon;
use League\Fractal\TransformerAbstract;
use STS\Helpers\IdentityValidationHelper;
use STS\Models\SupportTicket;
use STS\Models\User;
use STS\Repository\TripRepository;
use STS\Repository\UserRepository;
use STS\Services\AdminUserProfileCounts;
use STS\Services\GeoService;
use STS\Services\Logic\TripsManager;
use STS\Services\Logic\UsersManager;
use STS\Services\MapboxDirectionsRouteService;
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
        $mapboxDirectionsRouteService = app(MapboxDirectionsRouteService::class);
        $tripRepository = new TripRepository($geoService, $mercadoPagoService, $mapboxDirectionsRouteService);
        $this->tripLogic = new TripsManager($tripRepository, new UsersManager(new UserRepository, $tripRepository));
    }

    /**
     * Turn this item object into a generic array.
     *
     * @return array
     */
    public function transform(User $user)
    {
        $lastConnection = $user->last_connection;
        $lastConnectionSerialized = ($lastConnection instanceof Carbon && $lastConnection->year > 0)
            ? $lastConnection->toDateTimeString()
            : '';

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
            'last_connection' => $lastConnectionSerialized,
            'accounts' => $user->accounts,
            'donations' => $user->donations,
            'has_pin' => intval($user->has_pin),
            'is_member' => intval($user->is_member),
            'banned' => intval($user->banned),
            'active' => intval($user->active),
            'do_not_alert_request_seat' => intval($user->do_not_alert_request_seat),
            'do_not_alert_accept_passenger' => intval($user->do_not_alert_accept_passenger),
            'do_not_alert_pending_rates' => intval($user->do_not_alert_pending_rates),
            'do_not_alert_pricing' => intval($user->do_not_alert_pricing),
            'monthly_donate' => intval($user->monthly_donate),
            'unaswered_messages_limit' => intval($user->unaswered_messages_limit),
            'autoaccept_requests' => intval($user->autoaccept_requests),
            'driver_is_verified' => intval($user->driver_is_verified),
            'driver_data_docs' => $user->driver_data_docs ? json_decode($user->driver_data_docs) : null,
            'references' => $user->references,
            'data_visibility' => $user->data_visibility,
            'facebook_profile_url' => $user->facebook_profile_url,
            'references_data' => $user->referencesReceived,
            // Identity validation: exposed to every user (all viewers of the profile)
            'identity_validated' => $user->identity_validated ? true : false,
            'identity_validated_at' => $user->identity_validated_at ? $user->identity_validated_at->toDateTimeString() : null,
            'identity_validation_type' => $user->identity_validation_type,
        ];

        if ($this->user && $user->id == $this->user->id) {
            // True when enforcement is active and this user must validate as a "new" user (created_at >= cutoff).
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
            $data['created_at'] = $user->created_at instanceof Carbon
                ? $user->created_at->toDateTimeString()
                : null;
            // bank data
            $data['account_number'] = $user->account_number;
            $data['account_type'] = $user->account_type;
            $data['account_bank'] = $user->account_bank;
            $data['on_boarding_view'] = $user->on_boarding_view;

            // Always include car information for admins or the user themselves
            $data['cars'] = $user->cars;
            $data['patente'] = $user->cars->first() ? $user->cars->first()->patente : null;
            $data['car_description'] = $user->cars->first() ? $user->cars->first()->description : null;
            $data['support_tickets_count'] = SupportTicket::countForUser($user->id);
            $profileCounts = app(AdminUserProfileCounts::class);
            $data['admin_trips_count'] = $profileCounts->tripsCount($this->user, $user);
            $data['admin_ratings_count'] = $profileCounts->ratingsCount($user->id);
            $data['phone_verified'] = intval($user->phone_verified);
            $data['phone_verified_at'] = $user->phone_verified_at instanceof Carbon
                ? $user->phone_verified_at->toDateTimeString()
                : null;
            $data['identity_validation_rejected_at'] = $user->identity_validation_rejected_at instanceof Carbon
                ? $user->identity_validation_rejected_at->toDateTimeString()
                : null;
            $data['identity_validation_reject_reason'] = $user->identity_validation_reject_reason;
            $data['validate_by_date'] = $user->validate_by_date
                ? $user->validate_by_date->format('Y-m-d')
                : null;
        }

        switch ($user->data_visibility) {
            case '0':
                // viaja conmigo
                if ($this->user && $this->tripLogic->shareTrip($this->user, $user)) {
                    $data['nro_doc'] = $user->nro_doc;
                    $data['email'] = $user->email;
                    $data['mobile_phone'] = $user->mobile_phone;
                    $data['cars'] = $user->cars;
                }
                break;
            case '1':
                // publico
                $data['nro_doc'] = $user->nro_doc;
                $data['email'] = $user->email;
                $data['mobile_phone'] = $user->mobile_phone;
                $data['cars'] = $user->cars;
                break;
            default:
                // privado
                break;
        }

        if ($user->state) {
            $data['state'] = $user->state;
        }

        return $data;
    }
}
