<?php

namespace STS\Contracts\Repository; 

interface User
{ 
    public function create(array $data);

    public function update($user, array $data);

    public function show($id);

    public function acceptTerms($user);

    public function updatePhoto($user, $filename);

    public function index();

    public function getUserBy($key, $value);

    public function addFriend($user, $friend, $provider = "");

    public function deleteFriend($user, $friend);

    public function friendList($user);

    public function storeResetToken($user, $token);

    public function deleteResetToken($key, $value);

    public function getUserByResetToken($token);

}