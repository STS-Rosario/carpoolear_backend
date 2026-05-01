<?php

namespace Tests\Unit\Models;

use STS\Models\SocialAccount;
use STS\Models\User;
use Tests\TestCase;

class SocialAccountTest extends TestCase
{
    public function test_fillable_contains_expected_mass_assignable_attributes(): void
    {
        $this->assertSame([
            'user_id',
            'provider_user_id',
            'provider',
        ], (new SocialAccount)->getFillable());
    }

    public function test_hidden_contains_created_at_and_updated_at(): void
    {
        $this->assertSame([
            'created_at',
            'updated_at',
        ], (new SocialAccount)->getHidden());
    }

    public function test_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $account = SocialAccount::query()->create([
            'user_id' => $user->id,
            'provider_user_id' => 'ext-'.uniqid('', true),
            'provider' => 'facebook',
        ]);

        $this->assertTrue($account->fresh()->user->is($user));
    }

    public function test_persists_provider_and_provider_user_id(): void
    {
        $user = User::factory()->create();
        $externalId = 'google-subject-'.uniqid('', true);

        $account = SocialAccount::query()->create([
            'user_id' => $user->id,
            'provider_user_id' => $externalId,
            'provider' => 'google',
        ]);

        $account = $account->fresh();
        $this->assertSame($externalId, $account->provider_user_id);
        $this->assertSame('google', $account->provider);
    }

    public function test_user_can_have_multiple_social_accounts(): void
    {
        $user = User::factory()->create();

        SocialAccount::query()->create([
            'user_id' => $user->id,
            'provider_user_id' => 'a-'.uniqid('', true),
            'provider' => 'facebook',
        ]);
        SocialAccount::query()->create([
            'user_id' => $user->id,
            'provider_user_id' => 'b-'.uniqid('', true),
            'provider' => 'google',
        ]);

        $this->assertSame(2, SocialAccount::query()->where('user_id', $user->id)->count());
    }

    public function test_to_array_hides_timestamps(): void
    {
        $user = User::factory()->create();
        $account = SocialAccount::query()->create([
            'user_id' => $user->id,
            'provider_user_id' => 'x-'.uniqid('', true),
            'provider' => 'apple',
        ]);

        $array = $account->toArray();
        $this->assertArrayNotHasKey('created_at', $array);
        $this->assertArrayNotHasKey('updated_at', $array);
        $this->assertArrayHasKey('provider', $array);
        $this->assertArrayHasKey('user_id', $array);
    }

    public function test_table_name_is_social_accounts(): void
    {
        $this->assertSame('social_accounts', (new SocialAccount)->getTable());
    }
}
