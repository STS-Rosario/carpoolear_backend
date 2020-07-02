<?php

namespace STS;

use Carbon\Carbon;
use STS\Entities\Trip;
use STS\Entities\Passenger;
use STS\Entities\Rating as RatingModel;
use Illuminate\Notifications\Notifiable;
use STS\Contracts\Repository\IRatingRepository;
// use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use STS\Services\Notifications\Models\DatabaseNotification;

class User extends Authenticatable
{
    use Notifiable;

    protected $table = 'users';

    const FRIEND_REQUEST = 0;

    const FRIEND_ACCEPTED = 1;

    const FRIEND_REJECT = 2;

    const FRIENDSHIP_SYSTEM = 0;

    const FRIENDSHIP_FACEBOOK = 1;

    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'terms_and_conditions',
        'birthday',
        'gender',
        'banned',
        'nro_doc',
        'description',
        'mobile_phone',
        'image',
        'active',
        'activation_token',
        'emails_notifications',
        'last_connection',
        'has_pin',
        'is_member',
        'monthly_donate',
        'unaswered_messages_limit',
        'do_not_alert_request_seat',
        'do_not_alert_accept_passenger',
        'do_not_alert_pending_rates',
        'autoaccept_requests',
        'driver_is_verified',
        'driver_data_docs',
        'account_number',
        'account_type',
        'account_bank',
        'data_visibility'
    ];

    protected $dates = [
        'last_connection',
        'created_at',
        'updated_at',
    ];

    protected $hidden = [
        'password', 
        'remember_token', 
        'terms_and_conditions'
    ];

    protected $cast = [
        'banned'               => 'boolean',
        'terms_and_conditions' => 'boolean',
        'active'               => 'boolean',
        'is_admin'             => 'boolean',
        'has_pin'              => 'boolean',
        'is_member'            => 'boolean',
        'monthly_donate'       => 'boolean',
        'do_not_alert_request_seat'       => 'boolean',
        'do_not_alert_accept_passenger'   => 'boolean',
        'do_not_alert_pending_rates'      => 'boolean',
        'driver_is_verified'      => 'boolean',
        'driver_data_docs'      => 'array',
    ];

    protected $appends = [
        'positive_ratings',
        'negative_ratings',
        'references'
    ];

    public function accounts()
    {
        return $this->hasMany('STS\Entities\SocialAccount', 'user_id');
    }

    public function devices()
    {
        return $this->hasMany('STS\Entities\Device', 'user_id');
    }

    public function age()
    {
        if ($this->birthday) {
            return Carbon::parse($this->birthday)->diff()->year;
        }
    }

    public function passenger()
    {
        return $this->hasMany('STS\Entities\Passenger', 'user_id');
    }

    public function cars()
    {
        return $this->hasMany('STS\Entities\Car', 'user_id');
    }

    public function subscriptions()
    {
        return $this->hasMany('STS\Entities\Subscription', 'user_id');
    }

    public function allFriends($state = null)
    {
        $friends = $this->belongsToMany('STS\User', 'friends', 'uid1', 'uid2')
                    ->withTimestamps();
        if ($state) {
            $friends->wherePivot('state', $state);
        }

        return $friends;
    }

    public function friends($state = null)
    {
        return $this->belongsToMany('STS\User', 'friends', 'uid1', 'uid2')
                    ->withTimestamps()
                    ->wherePivot('state', self::FRIEND_ACCEPTED);
    }

    public function relativeFriends()
    {
        $u = $this;

        return self::whereHas('friends.friends', function ($q) use ($u) {
            $q->whereId($u->id);
        })->get();
    }

    public function notifications()
    {
        return $this->hasMany(DatabaseNotification::class, 'user_id')->whereNull('deleted_at');
    }

    public function donations()
    {
        $donations = $this->hasMany("STS\Entities\Donation", 'user_id');
        $donations->where('month', '<=', date('Y-m-t 23:59:59'));
        $donations->where('month', '>=', date('Y-m-01 00:00:00'));

        return $donations;
    }

    public function unreadNotifications()
    {
        return $this->notifications()->whereNull('read_at');
    }

    public function trips($state = null)
    {
        $trips = $this->hasMany("STS\Entities\Trip", 'user_id');
        if ($state === Trip::FINALIZADO) {
            $trips->where('trip_date', '<', Carbon::Now()->toDateTimeString());
        } elseif ($state === Trip::ACTIVO) {
            $trips->where('trip_date', '>=', Carbon::Now()->toDateTimeString());
        }

        return $trips;
    }

    public function conversations()
    {
        return $this->belongsToMany('STS\Entities\Conversation', 'conversations_users', 'user_id', 'conversation_id')->withPivot('read');
    }

    public function tripsAsPassenger($state = null, $hours_range = null, $date = null)
    {
        $user_id = $this->id;
        $trips = Trip::whereHas('passenger', function ($q) use ($user_id) {
            $q->whereUserId($user_id);
            $q->whereRequestState(Passenger::STATE_ACCEPTED);
        });
        if ($state === Trip::FINALIZADO) {
            $trips->where('trip_date', '<', Carbon::Now());
        } elseif ($state === Trip::ACTIVO) {
            $trips->where('trip_date', '>=', Carbon::Now());
        }
        if ($hours_range) {
            $date = !$date ? Carbon::Now() : new Carbon($date);
            $start_date = $date->copy()->subHours($hours_range)->toDateTimeString();
            $end_date = $date->copy()->addHours($hours_range)->toDateTimeString();
            $trips->where('trip_date', '>=', $start_date);
            $trips->where('trip_date', '<=', $end_date);
        }

        return $trips;
    }

    public function pendingRequests ($hours_range = null, $date = null) {
        $user_id = $this->id;
        $trip_ids = $this->tripsRequested($hours_range, $date)->pluck('id')->toArray();
        $pendingRequests = Passenger::whereIn('trip_id', $trip_ids);
        $pendingRequests->where('user_id', $user_id);
        $pendingRequests->where(function($q) {
            $q->where('request_state', Passenger::STATE_PENDING);
            $q->orWhere('request_state', Passenger::STATE_WAITING_PAYMENT);         
        });
        return $pendingRequests;
    }

    public function tripsRequested ($hours_range = null, $date = null)
    {
        $user_id = $this->id;
        $trips_requested = Trip::whereHas('passenger', function ($q) use ($user_id) {
            $q->whereUserId($user_id);
            $q->where(function($q) {
                $q->where('request_state', Passenger::STATE_PENDING);
                $q->orWhere('request_state', Passenger::STATE_WAITING_PAYMENT);         
            });
        });
        if ($hours_range) {
            $date = !$date ? Carbon::Now() : new Carbon($date);
            $start_date = $date->copy()->subHours($hours_range)->toDateTimeString();
            $end_date = $date->copy()->addHours($hours_range)->toDateTimeString();
            $trips_requested->where('trip_date', '>=', $start_date);
            $trips_requested->where('trip_date', '<=', $end_date);
        }
        return $trips_requested;
    }

    public function referencesReceived()
    {
        return $this->hasMany('STS\Entities\References', 'user_id_to');
    }

    public function ratingGiven()
    {
        return $this->hasMany('STS\Entities\Rating', 'user_id_from')->where('available', 1);
        /* ->where('voted', 1)
        ->where('created_at', '<=', Carbon::Now()
        ->subDays(RatingModel::RATING_INTERVAL));*/
    }

    public function ratingReceived()
    {
        return $this->hasMany('STS\Entities\Rating', 'user_id_to')->where('available', 1);
        /* ->where('voted', 1)
        ->where('created_at', '<=', Carbon::Now()
        ->subDays(RatingModel::RATING_INTERVAL)); */
    }

    public function ratings($value = null)
    {
        $recived = $this->ratingReceived();
        if (! is_null($value)) {
            $recived->where('rating', $value);
        }

        return $recived;
    }

    public function getPositiveRatingsAttribute()
    {
        return $this->ratings(RatingModel::STATE_POSITIVO)->count();
    }

    public function getNegativeRatingsAttribute()
    {
        return $this->ratings(RatingModel::STATE_NEGATIVO)->count();
    }

    public function getReferencesAttribute()
    {
        return $this->referencesReceived()->count();
    }
}
