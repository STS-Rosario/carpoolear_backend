<?php

namespace STS\Services\Social;

use GuzzleHttp\Client;
use STS\Contracts\SocialProvider;

class AppleSocialProvider implements SocialProvider
{
    protected $facebook;

    protected $token;

    protected $error;

    public function __construct($token)
    {
        $this->token = $token;
        $this->client = new Client;
    }

    public function getProviderName(): string
    {
        return 'apple';
    }

    public function getUserData($data): ?array
    {
        \Log::info('getUserData'.json_encode($data));
        $name = 'Apple ID Anónimo';
        if (isset($data['fullName'])) {
            if (isset($data['fullName']['givenName'])) {
                $name = $data['fullName']['givenName'];
            }
            if (isset($data['fullName']['familyName'])) {
                $name = $name.' '.$data['fullName']['familyName'];
            }
        }

        return [
            'provider_user_id' => $data['user'],
            'email' => isset($data['email']) ? $data['email'] : null,
            'name' => $name,
            'gender' => null,
            'birthday' => null,
            'banned' => false,
            'terms_and_conditions' => false,
            'image' => null,
        ];
    }

    public function getUserFriends(): array
    {
        return [];
    }

    public function getError(): ?array
    {
        return $this->error;
    }

    private function request($url) {}
}
