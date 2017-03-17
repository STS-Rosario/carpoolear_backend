<?php

namespace STS\Contracts\Logic;

use STS\Entities\Car as CarModel;
use STS\User as UserModel;

interface Car
{
    public function create(UserModel $user, $data);

    public function update(UserModel $user, $id, $data);

    public function show(UserModel $user, $id);

    public function delete(UserModel $user, $id);

    public function index(UserModel $user);

    public function setErrors($errs);
    
    public function getErrors();
}
