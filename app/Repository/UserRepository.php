<?php

namespace STS\Repository; 

use STS\Entities\Trip;
use STS\User;
use Validator;

class UserRepository
{
    /**
     * Create a new user instance after a valid registration.
     *
     * @param  array  $data
     * @return User
     */
    public function create(array $data)
    {
        return User::create($data);
    }

    public function update($user, array $data)
    {
        return $user->update($data);
    }

    public function show($id)
    { 
        return User::find($id);
    }

    public function index()
    { 
        return User::all();
    }        

}