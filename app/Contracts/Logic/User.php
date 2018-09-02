<?php

namespace STS\Contracts\Logic;

interface User
{
    public function create(array $data);

    public function update($user, array $data);

    public function updatePhoto($user, $data);

    public function show($user, $profile_id);

    public function find($user_id);

    public function activeAccount($activation_token);

    public function resetPassword($user);

    public function changePassword($token, $data);

    public function setErrors($errs);

    public function getErrors();

    public function index($user, $search_text);

    public function registerDonation($user, $donation);
}
