<?php

namespace STS;

use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{ 
    protected $table = 'users'; 

    const FRIEND_REQUEST  = 0;
    const FRIEND_ACCEPTED = 1;
    const FRIEND_REJECT   = 2;
    
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
		'patente',
		'descripcion',
		'mobile_phone',
		'image'
	];
	protected $hidden = ['password', 'remember_token'];
	protected $cast = [
		'banned' => 'boolean',
		'terms_and_conditions' => 'boolean'
	];

	public function accounts() 
	{ 
		return $this->hasMany('STS\Entities\SocialAccount', 'user_id');
	}

	public function age() {
		if ($this->birthday) {
			return Carbon::parse($this->birthday)->diff()->year;
		}
	}

	public function allFriends($state = null) 
    {
        $friends = $this->belongsToMany('STS\User', 'friends', 'uid1', 'uid2')
		            ->withTimestamps();
		if ($state) {
			$friends->where('state', $state);
		}			
		return $friends;
    } 

	public function friends($state = null) 
    { 
        return $this->belongsToMany('STS\User', 'friends', 'uid1', 'uid2')
		            ->withTimestamps()
					->where("state", User::FRIEND_ACCEPTED);
    } 

    public function relativeFriends()
    {
        $u = $this;
        return User::whereHas("friends.friends", function ($q) use ($u) {
            $q->whereId($u->id);
        })->get();
    }

    public function trips($state = null)
    {
        $trips = $this->hasMany("STS\Entities\Trip","user_id");
		if ($state == Trip::FINALIZADO ) {
			$trips->where("trip_date", "<", Carbon::Now());
		} else if ($state == Trip::ACTIVO) {
			$trips->where("trip_date", ">=", Carbon::Now());
		}
		return $trips;
    }

	public function tripsAsPassenger($state = null)
    {
		$user_id = $this->id;
		$trips =  Trip::whereHas('passenger',function ($q) use ($user_id) {
			$q->whereUserId($user_id);
			$q->whereRequestState(Passenger::STATE_ACEPTADO);
		});      
		if ($state == Trip::FINALIZADO ) {
			$trips->where("trip_date", "<", Carbon::Now());
		} else if ($state == Trip::ACTIVO) {
			$trips->where("trip_date", ">=", Carbon::Now());
		}
		return $trips;
    }


}
