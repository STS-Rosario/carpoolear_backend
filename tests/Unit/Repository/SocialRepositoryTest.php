<?php

namespace Tests\Unit\Repository;

use STS\Models\SocialAccount;
use STS\Models\User;
use STS\Repository\SocialRepository;
use Tests\TestCase;

class SocialRepositoryTest extends TestCase
{
    public function test_set_default_provider_and_get_provider_returns_instance_provider(): void
    {
        $repo = new SocialRepository;
        $repo->setDefaultProvider('facebook');

        $this->assertSame('facebook', $repo->getProvider('ignored_argument'));
    }

    public function test_constructor_sets_default_provider(): void
    {
        $repo = new SocialRepository('google');

        $this->assertSame('google', $repo->getProvider('x'));
    }

    public function test_find_uses_default_provider_when_argument_null(): void
    {
        $user = User::factory()->create();
        SocialAccount::create([
            'user_id' => $user->id,
            'provider_user_id' => 'pid-abc',
            'provider' => 'facebook',
        ]);

        $repo = new SocialRepository('facebook');
        $found = $repo->find('pid-abc', null);

        $this->assertNotNull($found);
        $this->assertSame('pid-abc', $found->provider_user_id);
    }

    public function test_find_returns_null_when_no_account_matches_subject(): void
    {
        // Mutation intent: preserve `SocialAccount::whereProvider(...)->whereProviderUserId(...)->first()` empty row (~34–38).
        $repo = new SocialRepository('facebook');

        $this->assertNull($repo->find('missing-subject-'.uniqid('', true), null));
    }

    public function test_find_with_explicit_provider_overrides_default(): void
    {
        $user = User::factory()->create();
        SocialAccount::create([
            'user_id' => $user->id,
            'provider_user_id' => 'same-id',
            'provider' => 'github',
        ]);

        $repo = new SocialRepository('facebook');
        $this->assertNull($repo->find('same-id', null));

        $found = $repo->find('same-id', 'github');
        $this->assertNotNull($found);
        $this->assertSame('github', $found->provider);
    }

    public function test_create_associates_user_and_persists_with_default_provider(): void
    {
        $user = User::factory()->create();
        $repo = new SocialRepository('twitter');
        $repo->create($user, 'tw-555', null);

        $this->assertDatabaseHas('social_accounts', [
            'user_id' => $user->id,
            'provider_user_id' => 'tw-555',
            'provider' => 'twitter',
        ]);
    }

    public function test_create_respects_explicit_provider_over_repository_default(): void
    {
        // Mutation intent: preserve `if (is_null($provider)) { $provider = $this->provider; }` so explicit third argument wins.
        $user = User::factory()->create();
        $repo = new SocialRepository('twitter');
        $repo->create($user, 'oauth-subject-99', 'linkedin');

        $this->assertDatabaseHas('social_accounts', [
            'user_id' => $user->id,
            'provider_user_id' => 'oauth-subject-99',
            'provider' => 'linkedin',
        ]);
    }

    public function test_delete_removes_social_account(): void
    {
        $user = User::factory()->create();
        $account = SocialAccount::create([
            'user_id' => $user->id,
            'provider_user_id' => 'del-me',
            'provider' => 'apple',
        ]);
        $id = $account->id;

        (new SocialRepository)->delete($account);

        $this->assertNull(SocialAccount::query()->find($id));
    }

    public function test_get_returns_all_accounts_or_filters_by_provider(): void
    {
        $user = User::factory()->create();
        SocialAccount::create([
            'user_id' => $user->id,
            'provider_user_id' => 'a1',
            'provider' => 'facebook',
        ]);
        SocialAccount::create([
            'user_id' => $user->id,
            'provider_user_id' => 'b1',
            'provider' => 'google',
        ]);

        $repo = new SocialRepository;
        $all = $repo->get($user->fresh());
        $this->assertCount(2, $all);

        $googleOnly = $repo->get($user->fresh(), 'google');
        $this->assertCount(1, $googleOnly);
        $this->assertSame('google', $googleOnly->first()->provider);
    }
}
