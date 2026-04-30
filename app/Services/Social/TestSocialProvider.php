<?php

namespace STS\Services\Social;

use STS\Contracts\SocialProvider;

/**
 * Deterministic social provider for automated tests (URL segment `test`).
 * The `access_token` body must be JSON with at least `provider_user_id`.
 */
class TestSocialProvider implements SocialProvider
{
    protected string $token;

    public function __construct($token)
    {
        $this->token = (string) $token;
    }

    public function getProviderName(): string
    {
        return 'test';
    }

    public function getUserData($data): ?array
    {
        $decoded = json_decode($this->token, true);
        if (! is_array($decoded) || empty($decoded['provider_user_id'])) {
            return null;
        }

        $row = [
            'provider_user_id' => (string) $decoded['provider_user_id'],
            'email' => $decoded['email'] ?? ('social+'.md5((string) $decoded['provider_user_id']).'@example.test'),
            'name' => $decoded['name'] ?? 'Social Test User',
            'gender' => 'N/A',
            'birthday' => null,
            'banned' => (bool) ($decoded['banned'] ?? false),
            'terms_and_conditions' => true,
        ];

        if (isset($decoded['description'])) {
            $row['description'] = $decoded['description'];
        }

        return $row;
    }

    /**
     * @return list<string>
     */
    public function getUserFriends(): array
    {
        $decoded = json_decode($this->token, true);
        if (! is_array($decoded)) {
            return [];
        }
        $friends = $decoded['friend_ids'] ?? [];
        if (! is_array($friends)) {
            return [];
        }

        return array_map('strval', $friends);
    }

    public function getError(): ?array
    {
        return null;
    }
}
