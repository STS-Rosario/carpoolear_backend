<?php

namespace STS\Contracts;

interface SocialProvider
{
    public function getProviderName();

    public function getUserData();

    public function getUserFriends();

    public function getError();
}
