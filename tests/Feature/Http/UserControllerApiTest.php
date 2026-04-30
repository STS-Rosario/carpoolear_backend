<?php

namespace Tests\Feature\Http;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Mockery;
use STS\Http\Controllers\Api\v1\UserController;
use STS\Models\Badge;
use STS\Models\User;
use STS\Services\AnonymizationService;
use STS\Services\Logic\DeviceManager;
use STS\Services\Logic\UsersManager;
use STS\Services\UserDeletionService;
use Tests\TestCase;

class UserControllerApiTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_constructor_registers_expected_logged_and_logged_optional_middleware_scopes(): void
    {
        $controller = new UserController(
            Mockery::mock(UsersManager::class),
            Mockery::mock(DeviceManager::class),
            Mockery::mock(UserDeletionService::class),
            Mockery::mock(AnonymizationService::class)
        );

        $middlewares = $controller->getMiddleware();

        $logged = collect($middlewares)->first(function ($entry) {
            return (is_array($entry) ? ($entry['middleware'] ?? null) : ($entry->middleware ?? null)) === 'logged';
        });
        $loggedOptional = collect($middlewares)->first(function ($entry) {
            return (is_array($entry) ? ($entry['middleware'] ?? null) : ($entry->middleware ?? null)) === 'logged.optional';
        });

        $this->assertNotNull($logged);
        $this->assertNotNull($loggedOptional);

        $loggedOptions = is_array($logged) ? ($logged['options'] ?? []) : ($logged->options ?? []);
        $loggedOptionalOptions = is_array($loggedOptional) ? ($loggedOptional['options'] ?? []) : ($loggedOptional->options ?? []);

        $this->assertSame(['create', 'registerDonation', 'bankData', 'terms'], $loggedOptions['except'] ?? []);
        $this->assertSame(['create', 'registerDonation', 'bankData', 'terms'], $loggedOptionalOptions['only'] ?? []);
    }

    public function test_user_list_requires_authentication(): void
    {
        $this->getJson('api/users/list')
            ->assertUnauthorized()
            ->assertJson(['message' => 'Unauthorized.']);
    }

    public function test_profile_update_requires_authentication(): void
    {
        $this->putJson('api/users/', ['name' => 'Nobody'])
            ->assertUnauthorized()
            ->assertJson(['message' => 'Unauthorized.']);
    }

    public function test_bank_data_and_terms_are_public(): void
    {
        $bank = $this->get('api/users/bank-data');
        $bank->assertOk();
        $decoded = json_decode($bank->getContent(), true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('banks', $decoded);
        $this->assertArrayHasKey('cc', $decoded);

        $terms = $this->get('api/users/terms');
        $terms->assertOk();
        $termsDecoded = json_decode($terms->getContent(), true);
        $this->assertIsArray($termsDecoded);
        $this->assertArrayHasKey('content', $termsDecoded);
        $this->assertNotEmpty($termsDecoded['content']);
    }

    public function test_registration_does_not_issue_token_for_inactive_account(): void
    {
        $email = 'new-inactive-'.uniqid('', true).'@example.com';
        $response = $this->postJson('api/users/', [
            'name' => 'Inactive Registrant',
            'email' => $email,
            'password' => 'secret12',
            'password_confirmation' => 'secret12',
        ]);

        $response->assertOk();
        $response->assertJsonMissingPath('token');
        $this->assertDatabaseHas('users', [
            'email' => $email,
            'active' => 0,
        ]);
    }

    public function test_registration_uses_viewing_user_for_profile_contract_when_logged_in(): void
    {
        $inviter = User::factory()->create([
            'is_admin' => false,
            'active' => true,
            'banned' => false,
        ]);

        $email = 'child-'.uniqid('', true).'@example.com';
        $this->actingAs($inviter, 'api');

        $response = $this->postJson('api/users/', [
            'name' => 'Invited Registrant',
            'email' => $email,
            'password' => 'secret12',
            'password_confirmation' => 'secret12',
        ]);

        $response->assertOk();
        $payload = $response->json('data');
        $this->assertIsArray($payload);
        $this->assertArrayNotHasKey('email', $payload);
        $this->assertSame(User::where('email', $email)->value('id'), $payload['id']);
    }

    public function test_validated_drivers_registration_persists_uploaded_doc_references(): void
    {
        config(['carpoolear.module_validated_drivers' => true]);

        $email = 'with-driver-docs-'.uniqid('', true).'@example.com';
        $file = UploadedFile::fake()->image('license.jpg', 80, 80);

        $response = $this->post('api/users/', [
            'name' => 'Driver Registrant',
            'email' => $email,
            'password' => 'secret12',
            'password_confirmation' => 'secret12',
            'driver_data_docs' => [$file],
        ]);

        $response->assertOk();
        $user = User::where('email', $email)->first();
        $this->assertNotNull($user);
        $this->assertNotEmpty($user->driver_data_docs);
        $decoded = json_decode($user->driver_data_docs, true);
        $this->assertIsArray($decoded);
        $this->assertNotEmpty($decoded[0]);
    }

    public function test_update_with_driver_intent_requires_documents_when_module_enabled(): void
    {
        config(['carpoolear.module_validated_drivers' => true]);

        $user = User::factory()->create([
            'driver_is_verified' => false,
            'active' => true,
            'banned' => false,
        ]);

        $this->actingAs($user, 'api');

        $this->putJson('api/users/', [
            'name' => $user->name,
            'user_be_driver' => 1,
        ])->assertStatus(422);
    }

    public function test_update_without_driver_intent_succeeds_when_module_enabled_and_user_not_verified(): void
    {
        config(['carpoolear.module_validated_drivers' => true]);

        $user = User::factory()->create([
            'driver_is_verified' => false,
            'active' => true,
            'banned' => false,
        ]);

        $this->actingAs($user, 'api');

        $this->putJson('api/users/', [
            'description' => 'Bio without declaring driver intent.',
        ])->assertOk();

        $this->assertSame('Bio without declaring driver intent.', $user->fresh()->description);
    }

    public function test_update_bans_user_when_mobile_matches_configured_fragment(): void
    {
        config(['carpoolear.banned_phones' => ['0009']]);

        $user = User::factory()->create([
            'active' => true,
            'banned' => false,
            'mobile_phone' => '08001112233',
        ]);

        $this->actingAs($user, 'api');

        $this->putJson('api/users/', [
            'description' => $user->description,
            'mobile_phone' => '+54 11 6000-9000-0009',
        ])->assertOk();

        $this->assertSame(1, (int) $user->fresh()->banned);
    }

    public function test_user_index_accepts_optional_search_value(): void
    {
        $actor = User::factory()->create(['active' => true, 'banned' => false]);
        $this->actingAs($actor, 'api');

        $this->getJson('api/users/list')
            ->assertOk()
            ->assertJsonStructure(['data']);

        $this->getJson('api/users/list?value='.urlencode(substr($actor->name, 0, 4)))
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_user_search_with_name_query_returns_collection_envelope(): void
    {
        $actor = User::factory()->create(['active' => true, 'banned' => false]);
        $this->actingAs($actor, 'api');

        $needle = substr($actor->name, 0, max(3, strlen($actor->name)));
        $this->getJson('api/users/search?name='.urlencode($needle))
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_show_other_user_by_numeric_id_returns_profile_envelope(): void
    {
        $viewer = User::factory()->create(['active' => true, 'banned' => false]);
        $other = User::factory()->create(['active' => true, 'banned' => false]);

        $this->actingAs($viewer, 'api');

        $this->getJson('api/users/'.$other->id)
            ->assertOk()
            ->assertJsonPath('data.id', $other->id);
    }

    public function test_badges_endpoint_returns_visible_badges_only(): void
    {
        $user = User::factory()->create(['active' => true, 'banned' => false]);
        $visible = Badge::create([
            'title' => 'Visible',
            'slug' => 'visible-'.uniqid(),
            'description' => 'd',
            'image_path' => '/x.png',
            'rules' => [],
            'visible' => true,
        ]);
        $hidden = Badge::create([
            'title' => 'Hidden',
            'slug' => 'hidden-'.uniqid(),
            'description' => 'd',
            'image_path' => '/y.png',
            'rules' => [],
            'visible' => false,
        ]);

        $now = now();
        $user->badges()->attach($visible->id, ['awarded_at' => $now]);
        $user->badges()->attach($hidden->id, ['awarded_at' => $now]);

        $this->actingAs($user, 'api');

        $this->getJson('api/users/'.$user->id.'/badges')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Visible');
    }
}
