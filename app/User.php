<?php namespace STS;

use Illuminate\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;

use STS\Entities\Trip;
use STS\Entities\Passenger;
use Carbon\Carbon;

class User extends Model implements AuthenticatableContract, CanResetPasswordContract {
	use Authenticatable, CanResetPassword;
	protected $table = 'users';
	protected $fillable = [
		'name', 
		'username',
		'email', 
		'password',
		//'facebook_uid',
		'username',
		'terms_and_conditions',
		'birthday',
		'gender',
		'banned',
		'nro_doc',
		'patente',
		'descripcion',
		'mobile_phone',
		'l_perfil'
	];
	protected $hidden = ['password', 'remember_token'];

	public function age() {
		if ($this->birthday) {
			return Carbon\Carbon::parse($this->birthday)->diff()->year;
		}
	}

	public function friends() 
    {
        return $this->belongsToMany('STS\User', 'friends', 'uid1', 'uid2');
    } 

    public function relativeFriends()
    {
        $u = $this;
        return User::whereHas("friends.friends", function ($q) use ($u) {
            $q->whereId($u->id);
        })->get();
    }

    public function trips()
    {
        return $this->hasMany("App\Entities\Trip","user_id");
    }

	public function tripsAsPassenger()
    {
		$user_id = $this->id;
		return Trip::whereHas('passenger',function ($q) use ($user_id) {
			$q->whereUserId($user_id);
			$q->whereRequestState(Passenger::STATE_ACEPTADO);
		});        
    }

	public function cantidadViajes($type = null)
	{
		$cantidad = 0;
		if ($type == Passenger::TYPE_CONDUCTOR || is_null($type)) {
			$cantidad += $this->trips()->where("trip_date","<",Carbon::Now())->count();
		}
		if ($type == Passenger::TYPE_PASAJERO || is_null($type)) {
			$cantidad += $this->tripsAsPassenger()->where("trip_date","<",Carbon::Now())->count();
		}
		return $cantidad;
	}

	public function distanciaRecorrida($type = null)
	{
		$distancia = 0;
		if ($type == Passenger::TYPE_CONDUCTOR || is_null($type)) {
			$distancia += $this->trips()->where("trip_date","<",Carbon::Now())->sum("distance");
		}
		if ($type == Passenger::TYPE_PASAJERO || is_null($type)) {
			$distancia += $this->tripsAsPassenger()->where("trip_date","<",Carbon::Now())->sum("distance");
		}
		return $distancia;
	}

}
