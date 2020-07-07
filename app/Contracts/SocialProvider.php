<?php

namespace STS\Contracts;

interface SocialProvider
{
    public function getProviderName();

    public function getUserData($data);

    public function getUserFriends();

    public function getError();
}
