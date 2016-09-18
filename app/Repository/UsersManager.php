<?php

namespace STS\Repository; 

use STS\Entities\Trip;
use STS\User;
use Validator;

class UsersManager
{
        /**
     * Get a validator for an incoming registration request.
     *
     * @param  array  $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    public function validator(array $data)
    {
        return Validator::make($data, [
            'name' => 'required|max:255',
            'email' => 'required|email|max:255|unique:users',
            'password' => 'min:6|confirmed',
            
        ]);
    }

    /**
     * Create a new user instance after a valid registration.
     *
     * @param  array  $data
     * @return User
     */
    public function create(array $data)
    {
        return User::create([
            'name' => $data['name'],
            'username' => null,
            'email' => $data['email'],
            'password' => isset($data['password']) ? bcrypt($data['password']) : null,
            //'image' => isset($data['image']) ? $data['image'] : null,

            'gender' => isset($data['gender']) ? $data['gender'] : "", 
            'birthday' => isset($data['birdthday']) ? $data['birdthday'] : null, 

            'nro_doc' => isset($data['nro_doc']) ? $data['nro_doc'] : "", 
            'patente' => isset($data['patente']) ? $data['patente'] : "", 
            'descripcion' => isset($data['descripcion']) ? $data['descripcion'] : "", 
            'mobile_phone' => isset($data['mobile_phone']) ? $data['mobile_phone'] : "", 
            'l_perfil' => isset($data['l_perfil']) ? $data['l_perfil'] : "", 

            'terms_and_conditions' => 0,
            'banned' => 0

        ]);
    }

    public function update($user, array $data)
    {
        if (isset($data['name'])) {
            $user->name = $data['name'];
        }
        if (isset($data['email'])) {
            $user->email = $data['email'];
        } 

        if (isset($data['nro_doc'])) {
            $user->nro_doc = $data['nro_doc'];
        }
        if (isset($data['patente'])) {
            $user->patente = $data['patente'];
        } 
        if (isset($data['descripcion'])) {
            $user->descripcion = $data['descripcion'];
        }
        if (isset($data['mobile_phone'])) {
            $user->mobile_phone = $data['mobile_phone'];
        }
        if (isset($data['l_perfil'])) {
            $user->l_perfil = $data['l_perfil'];
        }  
        return $user->save();
    }


    public static function acceptTerms($user) 
    {
        $user->terms_and_conditions = true;
        $user->save();
        return $user;
    }

    public function show($user, $profile)
    { 
        $profile->cantidadViajes = $profile->cantidadViajes();
        $profile->distanciaRecorrida = $profile->distanciaRecorrida();
        if ($user->id != $profile->id) {
            $user_id = $user->id;
            $patente = $profile->trips()->whereHas('passenger',function ($q) use ($user_id) {
                $q->whereUserId($user_id);
                $q->whereRequestState(Passenger::STATE_ACEPTADO);
            })->first();
            if (is_null($patente)) {
                $profile->patente = null;
                $user_id = $profile->id;
                $dni = $user->trips()->whereHas('passenger',function ($q) use ($user_id) {
                    $q->whereUserId($user_id);
                    $q->whereRequestState(Passenger::STATE_ACEPTADO);
                })->first();
                if (is_null($dni)) {
                    $profile->nro_doc = null;
                }
            }
        }

        return response()->json($profile);
    }

}