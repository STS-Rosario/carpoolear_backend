<?php

namespace Tests\Unit\Services\Social;

use STS\Services\Social\TestSocialProvider;
use Tests\TestCase;

class TestSocialProviderTest extends TestCase
{
    public function test_get_user_data_returns_null_when_token_is_invalid_json(): void
    {
        $p = new TestSocialProvider('{not-json');

        $this->assertNull($p->getUserData([]));
    }

    public function test_get_user_data_returns_null_when_provider_user_id_missing_or_empty(): void
    {
        $this->assertNull((new TestSocialProvider(json_encode(['email' => 'x'])))->getUserData([]));
        $this->assertNull((new TestSocialProvider(json_encode(['provider_user_id' => ''])))->getUserData([]));
    }

    public function test_get_user_data_returns_row_and_optional_description(): void
    {
        $token = json_encode([
            'provider_user_id' => 42,
            'email' => 'u@example.test',
            'name' => 'Unit',
            'description' => 'Bio',
        ]);
        $row = (new TestSocialProvider($token))->getUserData([]);

        $this->assertSame('42', $row['provider_user_id']);
        $this->assertSame('u@example.test', $row['email']);
        $this->assertSame('Unit', $row['name']);
        $this->assertSame('Bio', $row['description']);
        $this->assertTrue($row['terms_and_conditions']);
    }

    public function test_get_user_friends_returns_empty_when_token_not_array(): void
    {
        $this->assertSame([], (new TestSocialProvider('null'))->getUserFriends());
    }

    public function test_get_user_friends_returns_empty_when_friend_ids_not_array(): void
    {
        $token = json_encode(['provider_user_id' => '1', 'friend_ids' => 'nope']);

        $this->assertSame([], (new TestSocialProvider($token))->getUserFriends());
    }

    public function test_get_user_friends_stringifies_ids(): void
    {
        $token = json_encode(['provider_user_id' => '1', 'friend_ids' => [10, '20']]);

        $this->assertSame(['10', '20'], (new TestSocialProvider($token))->getUserFriends());
    }
}
