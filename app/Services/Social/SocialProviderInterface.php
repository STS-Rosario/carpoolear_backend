<?php

namespace STS\Services\Social; 

interface SocialProviderInterface {

    public function getProviderName();

    public function getUserData();

    public function getUserFriends();

    public function getError();

}