<?php

namespace Tests\Unit\Services\Logic;

use Mockery;
use STS\Contracts\SocialProvider;
use STS\Models\SocialAccount;
use STS\Models\User;
use STS\Repository\FileRepository;
use STS\Repository\SocialRepository;
use STS\Services\Logic\FriendsManager;
use STS\Services\Logic\SocialManager;
use STS\Services\Logic\UsersManager;
use STS\Services\UserEditablePropertiesService;
use Tests\TestCase;

final class FakeSocialProvider implements SocialProvider
{
    /**
     * @param  array<string, mixed>  $userData
     * @param  list<string>  $friendProviderIds
     */
    public function __construct(
        private string $providerName,
        private array $userData,
        private array $friendProviderIds = [],
    ) {}

    public function getProviderName(): string
    {
        return $this->providerName;
    }

    public function getUserData($data): ?array
    {
        return $this->userData;
    }

    public function getUserFriends(): array
    {
        return $this->friendProviderIds;
    }

    public function getError(): ?array
    {
        return null;
    }
}

class SocialManagerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * @return array{SocialManager, SocialRepository, \Mockery\MockInterface, \Mockery\MockInterface, \Mockery\MockInterface}
     */
    private function makeManager(
        SocialProvider $provider,
        ?SocialRepository $social = null,
    ): array {
        $social = $social ?? new SocialRepository($provider->getProviderName());
        $users = Mockery::mock(UsersManager::class);
        $friends = Mockery::mock(FriendsManager::class);
        $files = Mockery::mock(FileRepository::class);

        $manager = new SocialManager($provider, $users, $friends, $files, $social);

        return [$manager, $social, $users, $friends, $files];
    }

    public function test_validator_create_requires_name_email(): void
    {
        [$manager] = $this->makeManager(new FakeSocialProvider('facebook', []));

        $v = $manager->validator([]);
        $this->assertTrue($v->fails());
        $this->assertTrue($v->errors()->has('name'));
        $this->assertTrue($v->errors()->has('email'));

        $ok = $manager->validator([
            'name' => 'Ada Lovelace',
            'email' => 'ada-'.uniqid('', true).'@example.com',
        ]);
        $this->assertFalse($ok->fails());
    }

    public function test_validator_update_allows_partial_and_unique_email_rule_suffix(): void
    {
        [$manager] = $this->makeManager(new FakeSocialProvider('facebook', []));
        $user = User::factory()->create();

        $v = $manager->validator(['email' => 'not-an-email'], $user->id);
        $this->assertTrue($v->fails());

        $v2 = $manager->validator(['name' => 'Short'], $user->id);
        $emailRules = $v2->getRules()['email'];
        $this->assertIsArray($emailRules);
        $this->assertStringContainsString((string) $user->id, implode('|', $emailRules));
    }

    public function test_login_or_create_returns_existing_linked_user(): void
    {
        $user = User::factory()->create(['image' => 'existing-avatar.jpg']);
        $pid = 'fb-'.substr(uniqid('', true), 0, 12);

        SocialAccount::create([
            'user_id' => $user->id,
            'provider_user_id' => $pid,
            'provider' => 'facebook',
        ]);

        $userData = [
            'provider_user_id' => $pid,
            'email' => $user->email,
            'name' => $user->name,
            'gender' => 'N/A',
            'birthday' => null,
            'banned' => false,
            'terms_and_conditions' => true,
        ];

        [$manager, , $users, $friends, $files] = $this->makeManager(
            new FakeSocialProvider('facebook', $userData),
        );

        $users->shouldNotReceive('create');
        $friends->shouldNotReceive('make');
        $files->shouldNotReceive('createFromData');

        $result = $manager->loginOrCreate(['token' => 'ignored']);
        $this->assertTrue($result->is($user));
    }

    public function test_login_or_create_populates_missing_image_for_existing_user(): void
    {
        $user = User::factory()->create(['image' => null]);
        $pid = 'fb-img-'.substr(uniqid('', true), 0, 10);

        SocialAccount::create([
            'user_id' => $user->id,
            'provider_user_id' => $pid,
            'provider' => 'facebook',
        ]);

        $userData = [
            'provider_user_id' => $pid,
            'email' => $user->email,
            'name' => $user->name,
            'image' => 'data://text/plain;base64,'.base64_encode('fake-image-bytes'),
            'gender' => 'N/A',
            'birthday' => null,
            'banned' => false,
            'terms_and_conditions' => true,
        ];

        [$manager, , $users, $friends, $files] = $this->makeManager(
            new FakeSocialProvider('facebook', $userData),
        );

        $users->shouldNotReceive('create');
        $friends->shouldNotReceive('make');
        $files->shouldReceive('createFromData')
            ->once()
            ->with('fake-image-bytes', 'jpg', 'image/profile/')
            ->andReturn('social/avatar.jpg');

        $result = $manager->loginOrCreate(['token' => 'ignored']);

        $this->assertTrue($result->is($user->fresh()));
        $this->assertSame('social/avatar.jpg', $result->fresh()->image);
    }

    public function test_login_or_create_creates_user_and_social_when_no_account(): void
    {
        $pid = 'fb-new-'.substr(uniqid('', true), 0, 12);
        $email = 'social-new-'.uniqid('', true).'@example.com';

        $userData = [
            'provider_user_id' => $pid,
            'email' => $email,
            'name' => 'Social Created',
            'gender' => 'N/A',
            'birthday' => null,
            'banned' => false,
            'terms_and_conditions' => true,
        ];

        $created = User::factory()->create(['email' => $email, 'name' => 'Social Created']);

        [$manager, $social, $users, $friends] = $this->makeManager(
            new FakeSocialProvider('facebook', $userData),
        );

        $users->shouldReceive('create')
            ->once()
            ->with(Mockery::on(fn ($d) => is_array($d)), false, true)
            ->andReturn($created);

        $friends->shouldReceive('make')->never();

        $result = $manager->loginOrCreate([]);
        $this->assertTrue($result->is($created));

        $this->assertNotNull($social->find($pid, 'facebook'));
    }

    public function test_link_account_creates_row_when_pid_free_and_user_has_no_accounts(): void
    {
        $user = User::factory()->create();
        $pid = 'link-'.substr(uniqid('', true), 0, 12);

        $userData = [
            'provider_user_id' => $pid,
            'email' => $user->email,
            'name' => $user->name,
            'gender' => 'N/A',
            'birthday' => null,
            'banned' => false,
            'terms_and_conditions' => true,
        ];

        [$manager] = $this->makeManager(new FakeSocialProvider('facebook', $userData));

        $this->assertTrue($manager->linkAccount($user));

        $this->assertDatabaseHas('social_accounts', [
            'user_id' => $user->id,
            'provider_user_id' => $pid,
            'provider' => 'facebook',
        ]);
    }

    public function test_link_account_fails_when_user_already_has_social_account(): void
    {
        $user = User::factory()->create();
        SocialAccount::create([
            'user_id' => $user->id,
            'provider_user_id' => 'existing-pid',
            'provider' => 'facebook',
        ]);

        $userData = [
            'provider_user_id' => 'other-pid',
            'email' => $user->email,
            'name' => $user->name,
            'gender' => 'N/A',
            'birthday' => null,
            'banned' => false,
            'terms_and_conditions' => true,
        ];

        [$manager] = $this->makeManager(new FakeSocialProvider('facebook', $userData));

        $this->assertNull($manager->linkAccount($user));
        $this->assertSame('Ya tienes asociado un perfil', $manager->getErrors()['error']);
    }

    public function test_link_account_fails_when_provider_user_id_is_already_linked(): void
    {
        $existingOwner = User::factory()->create();
        $user = User::factory()->create();
        $pid = 'taken-pid-'.substr(uniqid('', true), 0, 8);
        SocialAccount::create([
            'user_id' => $existingOwner->id,
            'provider_user_id' => $pid,
            'provider' => 'facebook',
        ]);

        $userData = [
            'provider_user_id' => $pid,
            'email' => $user->email,
            'name' => $user->name,
            'gender' => 'N/A',
            'birthday' => null,
            'banned' => false,
            'terms_and_conditions' => true,
        ];

        [$manager] = $this->makeManager(new FakeSocialProvider('facebook', $userData));

        $this->assertNull($manager->linkAccount($user));
        $this->assertSame('Ya tienes asociado un perfil', $manager->getErrors()['error']);
    }

    public function test_update_profile_updates_when_account_matches_user(): void
    {
        $user = User::factory()->create(['name' => 'Before']);
        $pid = 'upd-'.substr(uniqid('', true), 0, 10);
        SocialAccount::create([
            'user_id' => $user->id,
            'provider_user_id' => $pid,
            'provider' => 'facebook',
        ]);

        $userData = [
            'provider_user_id' => $pid,
            'email' => $user->email,
            'name' => 'After',
            'gender' => 'N/A',
            'birthday' => null,
            'banned' => false,
            'terms_and_conditions' => true,
        ];

        [$manager, , $users, , $files] = $this->makeManager(
            new FakeSocialProvider('facebook', $userData),
        );

        $files->shouldNotReceive('createFromData');

        $filter = Mockery::mock(UserEditablePropertiesService::class);
        $filter->shouldReceive('filterForUser')
            ->once()
            ->with(Mockery::type('array'), false)
            ->andReturn(['name' => 'After']);
        $this->app->instance(UserEditablePropertiesService::class, $filter);

        $users->shouldReceive('update')
            ->once()
            ->with(
                Mockery::on(fn ($u) => $u->is($user)),
                Mockery::on(fn ($data) => isset($data['name']) && $data['name'] === 'After')
            )
            ->andReturnUsing(function ($u, $data) {
                $u->forceFill(['name' => $data['name']])->save();

                return $u->fresh();
            });

        $out = $manager->updateProfile($user->fresh());
        $this->assertSame('After', $out->name);
    }

    public function test_update_profile_propagates_errors_when_user_update_fails(): void
    {
        $user = User::factory()->create(['name' => 'Before']);
        $pid = 'upd-fail-'.substr(uniqid('', true), 0, 8);
        SocialAccount::create([
            'user_id' => $user->id,
            'provider_user_id' => $pid,
            'provider' => 'facebook',
        ]);

        $userData = [
            'provider_user_id' => $pid,
            'email' => $user->email,
            'name' => 'After',
            'gender' => 'N/A',
            'birthday' => null,
            'banned' => false,
            'terms_and_conditions' => true,
        ];

        [$manager, , $users, , $files] = $this->makeManager(
            new FakeSocialProvider('facebook', $userData),
        );

        $files->shouldNotReceive('createFromData');

        $filter = Mockery::mock(UserEditablePropertiesService::class);
        $filter->shouldReceive('filterForUser')
            ->once()
            ->with(Mockery::type('array'), false)
            ->andReturn(['name' => 'After']);
        $this->app->instance(UserEditablePropertiesService::class, $filter);

        $users->shouldReceive('update')
            ->once()
            ->andReturn(null);
        $users->shouldReceive('getErrors')
            ->once()
            ->andReturn(['error' => 'update_failed']);

        $out = $manager->updateProfile($user->fresh());

        $this->assertNull($out);
        $this->assertSame('update_failed', $manager->getErrors()['error']);
    }

    public function test_make_friends_calls_friends_manager_for_each_resolved_friend(): void
    {
        $user = User::factory()->create();
        $friendUser = User::factory()->create();
        $friendPid = 'f-'.substr(uniqid('', true), 0, 10);

        SocialAccount::create([
            'user_id' => $user->id,
            'provider_user_id' => 'self-pid',
            'provider' => 'facebook',
        ]);
        SocialAccount::create([
            'user_id' => $friendUser->id,
            'provider_user_id' => $friendPid,
            'provider' => 'facebook',
        ]);

        $userData = [
            'provider_user_id' => 'self-pid',
            'email' => $user->email,
            'name' => $user->name,
            'gender' => 'N/A',
            'birthday' => null,
            'banned' => false,
            'terms_and_conditions' => true,
        ];

        [$manager, , $users, $friends] = $this->makeManager(
            new FakeSocialProvider('facebook', $userData, [$friendPid]),
        );

        $users->shouldReceive('show')
            ->once()
            ->with(null, $friendUser->id)
            ->andReturn($friendUser);

        $friends->shouldReceive('make')
            ->once()
            ->with(
                Mockery::on(fn ($u) => $u->is($user)),
                Mockery::on(fn ($f) => $f->is($friendUser))
            )
            ->andReturn(true);

        $this->assertTrue($manager->makeFriends($user));
    }

    public function test_make_friends_sets_error_when_session_user_mismatch(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        SocialAccount::create([
            'user_id' => $other->id,
            'provider_user_id' => 'other-pid',
            'provider' => 'facebook',
        ]);

        $userData = [
            'provider_user_id' => 'other-pid',
            'email' => $other->email,
            'name' => $other->name,
            'gender' => 'N/A',
            'birthday' => null,
            'banned' => false,
            'terms_and_conditions' => true,
        ];

        [$manager, , , $friends] = $this->makeManager(
            new FakeSocialProvider('facebook', $userData),
        );

        $friends->shouldReceive('make')->never();

        $this->assertNull($manager->makeFriends($user));
        $this->assertSame('No tiene asociado ningun perfil', $manager->getErrors()['error']);
    }
}
