<?php

namespace STS\Contracts\Logic; 
 
interface User
{
  
    public function create(array $data);

    public function update($user, array $data);

    public function updatePhoto($user, $data);

    public function show($user, $profile_id);

}