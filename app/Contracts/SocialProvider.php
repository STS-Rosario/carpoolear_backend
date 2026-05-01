<?php

namespace STS\Contracts;

interface SocialProvider
{
    public function getProviderName(): string;

    /**
     * @param  mixed  $data  Provider-specific payload (e.g. OAuth callback data).
     * @return array<string, mixed>|null
     */
    public function getUserData($data);

    /**
     * @return list<string>
     */
    public function getUserFriends();

    /**
     * @return array<string, mixed>|null
     */
    public function getError();
}
