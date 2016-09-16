<?php namespace STS;

use Illuminate\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;

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
		'banned'
	];
	protected $hidden = ['password', 'remember_token'];

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

}
