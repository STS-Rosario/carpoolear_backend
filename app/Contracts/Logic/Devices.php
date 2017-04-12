<?php

namespace STS\Contracts\Logic;

use STS\User as UserModel;

interface Devices
{
    public function register(UserModel $user, array $data);

    public function updateBySession($session_id, array $data);

    public function update($user, $id, array $data);

    public function delete($token);

    public function getDevices(UserModel $user);

    public function setErrors($errs);

    public function getErrors();
}
