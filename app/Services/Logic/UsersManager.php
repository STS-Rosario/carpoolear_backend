<?php

namespace STS\Services\Logic;

use STS\User;
use Validator;
use STS\Entities\Trip;
use STS\Repository\FileRepository;
use STS\Events\User\Reset  as ResetEvent;
use STS\Contracts\Logic\User as UserLogic;
use STS\Events\User\Create as CreateEvent;
use STS\Events\User\Update as UpdateEvent;
use STS\Contracts\Repository\User as UserRep;

class UsersManager extends BaseManager implements UserLogic
{
    protected $repo;

    public function __construct(UserRep $userRep)
    {
        $this->repo = $userRep;
    }

    public function validator(array $data, $id = null, $is_social = false)
    {
        if ($id) {
            return Validator::make($data, [
                'name'     => 'max:255',
                'email'    => 'email|max:255|unique:users,email,'.$id,
                'password' => 'min:6|confirmed',
                // 'gender'   => 'string|in:Masculino,Femenino,N/A',
            ]);
        } else {
            if(!$is_social) {
                return Validator::make($data, [
                    'name'     => 'required|max:255',
                    'email'    => 'required|email|max:255|unique:users',
                    'password' => 'min:6|confirmed',
                    // 'gender'   => 'string|in:Masculino,Feminino,N/A',
                    'emails_notifications' => 'boolean',
                ]);
            } else {
                return Validator::make($data, [
                    'name'     => 'required|max:255',
                    'email'    => 'present|email|max:255|unique:users',
                    'password' => 'min:6|confirmed',
                    // 'gender'   => 'string|in:Masculino,Feminino,N/A',
                    'emails_notifications' => 'boolean',
                ]);
            }
        }
    }

    /**
     * Create a new user instance after a valid registration.
     *
     * @param array $data
     *
     * @return User
     */
    public function create(array $data, $validate = true, $is_social = false)
    {
        $v = $this->validator($data, null, $is_social);
        if ($v->fails() && $validate) {
            $this->setErrors($v->errors());

            return;
        } else {
            $data['emails_notifications'] = true;
            if (isset($data['password'])) {
                $data['password'] = bcrypt($data['password']);
            }
            if (! isset($data['active'])) {
                $data['active'] = false;
                $data['activation_token'] = str_random(40);
            }
            $u = $this->repo->create($data);
            event(new CreateEvent($u->id));

            return $u;
        }
    }

    public function update($user, array $data)
    {
        $v = $this->validator($data, $user->id);
        if ($v->fails()) {
            $this->setErrors($v->errors());

            return;
        } else {
            if (isset($data['password'])) {
                $data['password'] = bcrypt($data['password']);
            }

            $this->repo->update($user, $data);
            event(new UpdateEvent($user->id));

            return $user;
        }
    }

    public function updatePhoto($user, $data)
    {
        $v = Validator::make($data, ['profile' => 'required']);
        if ($v->fails()) {
            $this->setErrors($v->errors());

            return;
        } else {
            $fileManager = new FileRepository();
            $base64_string = $data['profile'];

            $data = explode(',', $base64_string);
            if (is_array($data) && count($data) > 1) {
                $data = base64_decode($data[1]);
    
                $name = $fileManager->createFromData($data, 'jpeg', 'image/profile');
                $this->repo->updatePhoto($user, $name);
                event(new UpdateEvent($user->id));
                return $user;
            } else {
                $error = new \stdClass();
                $error->error = 'error_uploading_image';
                $this->setErrors($error);
                return;
            }

        }
    }

    public function find($user_id)
    {
        return $this->repo->show($user_id);
    }

    public function activeAccount($activation_token)
    {
        $user = $this->repo->getUserBy('activation_token', $activation_token);
        if ($user) {
            $this->repo->update($user, ['active' => true, 'activation_token' => null]);

            return $user;
        } else {
            $this->setErrors(['error' => 'invalid_activation_token']);

            return;
        }
    }

    public function resetPassword($email)
    {
        $user = $this->repo->getUserBy('email', $email);
        if ($user) {
            $token = str_random(40);
            $this->repo->deleteResetToken('email', $user->email);
            $this->repo->storeResetToken($user, $token);
            $this->repo->update($user, ['active' => false]);
            event(new ResetEvent($user->id, $token));

            return $token;
        } else {
            $this->setErrors(['error' => 'user_not_found']);

            return;
        }
    }

    public function changePassword($token, $data)
    {
        $user = $this->repo->getUserByResetToken($token);
        if ($user) {
            $data['active'] = true;
            if ($this->update($user, $data)) {
                $this->repo->deleteResetToken('email', $user->email);

                return true;
            }
        }
    }

    public function show($user, $profile_id)
    {
        $profile = $this->repo->show($profile_id);
        if ($profile) {
            return $profile;
        }
        $this->setErrors(['error' => 'profile not found']);
    }

    public function index($user, $search_text)
    {
        return $this->repo->index($user, $search_text);
    }

    public function tripsCount($user, $type = null)
    {
        $cantidad = 0;
        if ($type == Passenger::TYPE_CONDUCTOR || is_null($type)) {
            $cantidad += $user->trips(Trip::FINALIZADO)->count();
        }
        if ($type == Passenger::TYPE_PASAJERO || is_null($type)) {
            $cantidad += $user->tripsAsPassenger(Trip::FINALIZADO)->count();
        }

        return $cantidad;
    }

    public function tripsDistance($user, $type = null)
    {
        $distancia = 0;
        if ($type == Passenger::TYPE_CONDUCTOR || is_null($type)) {
            $distancia += $user->trips(Trip::FINALIZADO)->sum('distance');
        }
        if ($type == Passenger::TYPE_PASAJERO || is_null($type)) {
            $distancia += $user->tripsAsPassenger(Trip::FINALIZADO)->sum('distance');
        }

        return $distancia;
    }
}
