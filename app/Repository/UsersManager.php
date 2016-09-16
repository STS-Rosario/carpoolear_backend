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

            'terms_and_conditions' => 0,
            'banned' => 0

        ]);
    }

}