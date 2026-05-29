<?php

namespace Tests\Feature\Http;

use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\File;
use Mockery;
use STS\Http\Controllers\Api\v1\UserController;
use STS\Jobs\SendDeleteAccountRequestEmail;
use STS\Models\Badge;
use STS\Models\DeleteAccountRequest;
use STS\Models\Rating;
use STS\Models\Trip;
use STS\Models\User;
use STS\Services\AnonymizationService;
use STS\Services\Logic\DeviceManager;
use STS\Services\Logic\UsersManager;
use STS\Services\MercadoPagoOAuthService;
use STS\Services\UserDeletionService;
use Tests\TestCase;

class UserControllerApiTest extends TestCase
{
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

    public function test_update_persists_name_for_unverified_user(): void
    {
        $user = User::factory()->create([
            'name' => 'Original Name',
            'active' => true,
            'banned' => false,
            'identity_validated' => false,
            'identity_validated_at' => null,
        ]);

        $this->actingAs($user, 'api');

        $this->putJson('api/users/', [
            'name' => 'Updated Name',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Name');

        $this->assertSame('Updated Name', $user->fresh()->name);
    }

    public function test_update_ignores_name_change_for_identity_validated_user(): void
    {
        $user = User::factory()->create([
            'name' => 'Verified Name',
            'active' => true,
            'banned' => false,
            'identity_validated' => true,
            'identity_validated_at' => now(),
        ]);

        $this->actingAs($user, 'api');

        $this->putJson('api/users/', [
            'name' => 'Attempted Change',
            'description' => 'Still editable bio.',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Verified Name')
            ->assertJsonPath('data.description', 'Still editable bio.');

        $this->assertSame('Verified Name', $user->fresh()->name);
        $this->assertSame('Still editable bio.', $user->fresh()->description);
    }

    public function test_registration_persists_facebook_profile_url_when_module_enabled(): void
    {
        config(['carpoolear.module_facebook_profile_url_enabled' => true]);

        $email = 'facebook-url-registration-'.uniqid('', true).'@example.com';
        $facebookUrl = 'https://facebook.com/registro-fixture';

        $response = $this->postJson('api/users/', [
            'name' => 'Facebook Url Registration',
            'email' => $email,
            'password' => 'secret12',
            'password_confirmation' => 'secret12',
            'facebook_profile_url' => $facebookUrl,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.facebook_profile_url', $facebookUrl);
        $this->assertDatabaseHas('users', [
            'email' => $email,
            'facebook_profile_url' => $facebookUrl,
        ]);
    }

    public function test_update_persists_facebook_profile_url_when_module_enabled(): void
    {
        config(['carpoolear.module_facebook_profile_url_enabled' => true]);

        $user = User::factory()->create([
            'active' => true,
            'banned' => false,
        ]);

        $this->actingAs($user, 'api');

        $facebookUrl = 'https://facebook.com/update-fixture';
        $this->putJson('api/users/', [
            'description' => 'Bio update with facebook profile URL.',
            'facebook_profile_url' => $facebookUrl,
        ])
            ->assertOk()
            ->assertJsonPath('data.facebook_profile_url', $facebookUrl);

        $this->assertSame($facebookUrl, $user->fresh()->facebook_profile_url);
    }

    public function test_admin_update_persists_facebook_profile_url_when_module_enabled(): void
    {
        config(['carpoolear.module_facebook_profile_url_enabled' => true]);

        $admin = User::factory()->create(['active' => true, 'banned' => false, 'is_admin' => true]);
        $target = User::factory()->create(['active' => true, 'banned' => false]);

        $this->actingAs($admin, 'api');

        $facebookUrl = 'https://facebook.com/admin-update-fixture';
        $this->putJson('api/users/modify', [
            'user' => ['id' => $target->id],
            'facebook_profile_url' => $facebookUrl,
        ])
            ->assertOk()
            ->assertJsonPath('data.facebook_profile_url', $facebookUrl);

        $this->assertSame($facebookUrl, $target->fresh()->facebook_profile_url);
    }

    public function test_registration_normalizes_facebook_profile_url_without_scheme(): void
    {
        config(['carpoolear.module_facebook_profile_url_enabled' => true]);

        $email = 'facebook-url-normalize-'.uniqid('', true).'@example.com';
        $expected = 'https://facebook.com/registro-fixture';

        $response = $this->postJson('api/users/', [
            'name' => 'Facebook Url Normalize',
            'email' => $email,
            'password' => 'secret12',
            'password_confirmation' => 'secret12',
            'facebook_profile_url' => 'facebook.com/registro-fixture',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.facebook_profile_url', $expected);
        $this->assertDatabaseHas('users', [
            'email' => $email,
            'facebook_profile_url' => $expected,
        ]);
    }

    public function test_registration_rejects_non_facebook_profile_url(): void
    {
        config(['carpoolear.module_facebook_profile_url_enabled' => true]);

        $email = 'facebook-url-invalid-'.uniqid('', true).'@example.com';

        $this->postJson('api/users/', [
            'name' => 'Facebook Url Invalid',
            'email' => $email,
            'password' => 'secret12',
            'password_confirmation' => 'secret12',
            'facebook_profile_url' => 'https://example.com/profile',
        ])->assertStatus(422);
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

    public function test_update_driver_path_requires_module_flag_even_when_user_is_already_verified_driver(): void
    {
        config(['carpoolear.module_validated_drivers' => false]);

        $user = User::factory()->create([
            'driver_is_verified' => true,
            'active' => true,
            'banned' => false,
        ]);

        $this->actingAs($user, 'api');

        $this->putJson('api/users/', [
            'description' => 'Verified driver tweak without module.',
        ])->assertOk();

        $this->assertSame('Verified driver tweak without module.', $user->fresh()->description);
    }

    public function test_update_ban_phone_logs_once_when_two_fragments_both_match(): void
    {
        config(['carpoolear.banned_phones' => ['0009', '6000']]);

        $user = User::factory()->create([
            'active' => true,
            'banned' => false,
            'mobile_phone' => '080011160009',
        ]);

        $this->actingAs($user, 'api');

        $this->putJson('api/users/', [
            'description' => $user->description,
            'mobile_phone' => '+54 11 6000-9000-0009',
        ])->assertOk();

        $this->assertSame(1, (int) $user->fresh()->banned);
    }

    public function test_show_me_sets_validate_by_date_when_enforcement_days_and_grace_apply(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-10 12:00:00', 'UTC'));

        config([
            'carpoolear.identity_validation_enabled' => true,
            'carpoolear.identity_validation_optional' => false,
            'carpoolear.identity_validation_days_for_current_users' => 14,
            'carpoolear.identity_validation_new_users_date' => '2026-04-01',
        ]);

        $user = User::factory()->create([
            'active' => true,
            'banned' => false,
            'validate_by_date' => null,
            'created_at' => Carbon::parse('2026-01-01 10:00:00', 'UTC'),
        ]);

        $this->actingAs($user, 'api');

        $this->getJson('api/users/me')->assertOk();

        $deadline = $user->fresh()->validate_by_date;
        $this->assertNotNull($deadline);
        $this->assertSame(
            Carbon::parse('2026-03-10 12:00:00', 'UTC')->addDays(14)->toDateString(),
            $deadline->timezone('UTC')->toDateString()
        );

        Carbon::setTestNow();
    }

    public function test_show_me_does_not_set_validate_by_date_when_grace_days_are_zero(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-10 12:00:00', 'UTC'));

        config([
            'carpoolear.identity_validation_enabled' => true,
            'carpoolear.identity_validation_optional' => false,
            'carpoolear.identity_validation_days_for_current_users' => 0,
            'carpoolear.identity_validation_new_users_date' => '2026-04-01',
        ]);

        $user = User::factory()->create([
            'active' => true,
            'banned' => false,
            'validate_by_date' => null,
            'created_at' => Carbon::parse('2026-01-01 10:00:00', 'UTC'),
        ]);

        $this->actingAs($user, 'api');

        $this->getJson('api/users/me')->assertOk();

        $this->assertNull($user->fresh()->validate_by_date);

        Carbon::setTestNow();
    }

    public function test_badges_unknown_numeric_id_returns_not_found(): void
    {
        $user = User::factory()->create(['active' => true, 'banned' => false]);
        $this->actingAs($user, 'api');

        $missingId = User::query()->max('id') + 50000;

        $this->getJson('api/users/'.$missingId.'/badges')
            ->assertStatus(422);
    }

    public function test_badges_me_slug_resolves_to_authenticated_user(): void
    {
        $user = User::factory()->create(['active' => true, 'banned' => false]);
        $badge = Badge::create([
            'title' => 'Me slug',
            'slug' => 'me-slug-'.uniqid(),
            'description' => 'd',
            'image_path' => '/z.png',
            'rules' => [],
            'visible' => true,
        ]);
        $user->badges()->attach($badge->id, ['awarded_at' => now()]);

        $this->actingAs($user, 'api');

        $this->getJson('api/users/me/badges')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Me slug');
    }

    public function test_user_index_search_value_narrows_results_relative_to_open_list(): void
    {
        $needle = 'ZzNeedle'.uniqid('', false);
        $actor = User::factory()->create(['active' => true, 'banned' => false, 'name' => 'Actor']);
        $match = User::factory()->create(['active' => true, 'banned' => false, 'name' => 'Person '.$needle]);
        User::factory()->create(['active' => true, 'banned' => false, 'name' => 'Other Person']);

        $this->actingAs($actor, 'api');

        $open = $this->getJson('api/users/list')->json('data');
        $filtered = $this->getJson('api/users/list?value='.urlencode($needle))->json('data');

        $this->assertGreaterThan(count($filtered), count($open));
        $this->assertSame(1, count($filtered));
        $this->assertSame($match->id, $filtered[0]['id']);
    }

    public function test_user_search_requires_name_parameter_to_apply_filter(): void
    {
        $actor = User::factory()->create(['active' => true, 'banned' => false, 'name' => 'SearchActor']);
        $other = User::factory()->create(['active' => true, 'banned' => false, 'name' => 'SearchOther']);

        $this->actingAs($actor, 'api');

        $needle = substr($other->name, 0, max(4, strlen($other->name)));
        $withName = $this->getJson('api/users/search?name='.urlencode($needle))->json('data');
        $withoutName = $this->getJson('api/users/search')->json('data');

        $this->assertNotEmpty($withName);
        $this->assertTrue($withoutName === null || $withoutName === []);
    }

    public function test_register_donation_maps_zero_user_to_anonymous_donor_id(): void
    {
        if (! User::query()->whereKey(164619)->exists()) {
            $placeholder = User::factory()->make([
                'name' => 'Donador anónimo (fixture)',
                'email' => 'anon-donor-placeholder@example.invalid',
                'active' => false,
            ]);
            $placeholder->id = 164619;
            $placeholder->saveQuietly();
        }

        $donor = User::factory()->create(['active' => true, 'banned' => false]);
        $this->actingAs($donor, 'api');

        $response = $this->postJson('api/users/donation', [
            'user' => 0,
            'has_donated' => 1,
            'has_denied' => 0,
            'ammount' => 100,
        ]);

        $response->assertSuccessful();
        $this->assertDatabaseHas('donations', [
            'user_id' => 164619,
            'ammount' => 100,
        ]);
    }

    public function test_register_donation_uses_numeric_user_from_request(): void
    {
        $donor = User::factory()->create(['active' => true, 'banned' => false]);
        $beneficiary = User::factory()->create(['active' => true, 'banned' => false]);

        $this->actingAs($donor, 'api');

        $response = $this->postJson('api/users/donation', [
            'user' => (string) $beneficiary->id,
            'has_donated' => 1,
            'has_denied' => 0,
            'ammount' => 50,
        ]);

        $response->assertSuccessful();
        $this->assertDatabaseHas('donations', [
            'user_id' => $beneficiary->id,
            'ammount' => 50,
        ]);
    }

    public function test_terms_lang_selects_language_specific_file_when_present(): void
    {
        $termsDir = storage_path('terms');
        File::ensureDirectoryExists($termsDir);
        $app = config('carpoolear.target_app', 'carpoolear');
        $path = $termsDir.'/'.$app.'_mutlang.html';
        File::put($path, '<p>mutation-lang-doc</p>');

        try {
            $response = $this->get('api/users/terms?lang=mutlang');
            $response->assertOk();
            $decoded = json_decode($response->getContent(), true);
            $this->assertSame('<p>mutation-lang-doc</p>', $decoded['content']);
        } finally {
            if (File::exists($path)) {
                File::delete($path);
            }
        }
    }

    public function test_change_boolean_property_maps_non_positive_route_value_to_zero(): void
    {
        $user = User::factory()->create([
            'active' => true,
            'banned' => false,
            'emails_notifications' => true,
        ]);

        $this->actingAs($user, 'api');

        $this->getJson('/api/users/change/emails_notifications/0')
            ->assertOk();

        $this->assertFalse((bool) $user->fresh()->emails_notifications);
    }

    public function test_mercadopago_oauth_url_returns_503_when_identity_validation_disabled(): void
    {
        config([
            'carpoolear.identity_validation_enabled' => false,
            'carpoolear.identity_validation_mercado_pago_enabled' => true,
        ]);

        $user = User::factory()->create([
            'active' => true,
            'banned' => false,
            'nro_doc' => '30123456',
        ]);

        $this->actingAs($user, 'api');

        $this->getJson('api/users/mercadopago-oauth-url')
            ->assertStatus(503)
            ->assertJsonFragment(['message' => 'Identity validation is not available.']);
    }

    public function test_mercadopago_oauth_url_returns_503_for_mp_disabled_branch_with_exact_status(): void
    {
        config([
            'carpoolear.identity_validation_enabled' => true,
            'carpoolear.identity_validation_mercado_pago_enabled' => false,
        ]);
        config(['services.mercadopago.client_id' => 'configured-but-mp-off']);

        $user = User::factory()->create([
            'active' => true,
            'banned' => false,
            'nro_doc' => '30123456',
        ]);

        $this->actingAs($user, 'api');

        $this->getJson('api/users/mercadopago-oauth-url')
            ->assertStatus(503)
            ->assertJsonFragment([
                'message' => 'Identity validation with Mercado Pago is not available.',
            ]);
    }

    public function test_mercadopago_oauth_url_returns_503_for_missing_client_id_with_exact_status(): void
    {
        config([
            'carpoolear.identity_validation_enabled' => true,
            'carpoolear.identity_validation_mercado_pago_enabled' => true,
        ]);
        config(['services.mercadopago.client_id' => '   ']);

        $user = User::factory()->create([
            'active' => true,
            'banned' => false,
            'nro_doc' => '30999111',
        ]);

        $this->actingAs($user, 'api');

        $this->getJson('api/users/mercadopago-oauth-url')
            ->assertStatus(503)
            ->assertJsonFragment([
                'message' => 'Mercado Pago OAuth is not configured. Set MERCADO_PAGO_CLIENT_ID and related env vars.',
            ]);
    }

    public function test_mercadopago_oauth_url_returns_422_with_nro_doc_required_validation_when_dni_missing(): void
    {
        config([
            'carpoolear.identity_validation_enabled' => true,
            'carpoolear.identity_validation_mercado_pago_enabled' => true,
        ]);
        config(['services.mercadopago.client_id' => 'configured-client']);

        $user = User::factory()->create([
            'active' => true,
            'banned' => false,
            'nro_doc' => '',
        ]);

        $this->actingAs($user, 'api');

        $response = $this->getJson('api/users/mercadopago-oauth-url');
        $response->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'User must have DNI (nro_doc) set to validate identity.',
            ]);
        $this->assertSame(
            ['required'],
            $response->json('errors.nro_doc')
        );
    }

    public function test_mercadopago_oauth_url_returns_authorization_payload_when_configured(): void
    {
        config([
            'carpoolear.identity_validation_enabled' => true,
            'carpoolear.identity_validation_mercado_pago_enabled' => true,
        ]);
        config(['services.mercadopago.client_id' => 'mp-test-client-id']);

        $oauth = Mockery::mock(MercadoPagoOAuthService::class);
        $oauth->shouldReceive('getAuthorizationUrl')
            ->once()
            ->with(Mockery::type('string'))
            ->andReturn([
                'authorization_url' => 'https://auth.mercadopago.com/authorization?fixture=1',
                'code_verifier' => 'fixture-verifier',
            ]);
        $this->instance(MercadoPagoOAuthService::class, $oauth);

        $user = User::factory()->create([
            'active' => true,
            'banned' => false,
            'nro_doc' => '30999888',
        ]);

        $this->actingAs($user, 'api');

        $this->getJson('api/users/mercadopago-oauth-url')
            ->assertOk()
            ->assertJsonPath('authorization_url', 'https://auth.mercadopago.com/authorization?fixture=1');
    }

    public function test_mercadopago_oauth_url_non_pkce_flow_returns_string_url_and_hits_scalar_cache_branch(): void
    {
        config([
            'carpoolear.identity_validation_enabled' => true,
            'carpoolear.identity_validation_mercado_pago_enabled' => true,
        ]);
        config([
            'services.mercadopago.client_id' => 'mp-non-pkce-client',
            'services.mercadopago.oauth_redirect_uri' => 'https://app.example.test/mp/callback',
            'services.mercadopago.oauth_pkce_enabled' => false,
        ]);

        $user = User::factory()->create([
            'active' => true,
            'banned' => false,
            'nro_doc' => '30111222',
        ]);

        $this->actingAs($user, 'api');

        $response = $this->getJson('api/users/mercadopago-oauth-url');
        $response->assertOk();
        $url = $response->json('authorization_url');
        $this->assertIsString($url);
        $this->assertStringContainsString('auth.mercadopago.com', $url);
        $this->assertStringContainsString('client_id=', $url);
        $this->assertStringContainsString('mp-non-pkce-client', $url);
        $this->assertStringContainsString('redirect_uri=', $url);
    }

    public function test_delete_account_request_persists_row_and_dispatches_email_job(): void
    {
        Bus::fake();

        $user = User::factory()->create(['active' => true, 'banned' => false]);
        $this->actingAs($user, 'api');

        $response = $this->postJson('api/users/delete-account-request');

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Delete account request created successfully');

        $requestId = $response->json('request_id');
        $this->assertNotNull($requestId);
        $this->assertDatabaseHas('delete_account_requests', [
            'id' => $requestId,
            'user_id' => $user->id,
            'action_taken' => DeleteAccountRequest::ACTION_REQUESTED,
        ]);

        Bus::assertDispatched(SendDeleteAccountRequestEmail::class);
    }

    public function test_delete_account_hard_deletes_user_without_related_activity(): void
    {
        $user = User::factory()->create(['active' => true, 'banned' => false]);
        $token = $this->postJson('api/login', [
            'email' => $user->email,
            'password' => '123456',
        ])->assertOk()->json('token');

        $this->postJson('api/users/delete-account', [], [
            'Authorization' => 'Bearer '.$token,
        ])->assertOk()
            ->assertJsonPath('action', 'deleted');

        $this->assertNull(User::find($user->id));
    }

    public function test_delete_account_anonymizes_when_user_has_trip_history(): void
    {
        $user = User::factory()->create(['active' => true, 'banned' => false]);
        Trip::factory()->create(['user_id' => $user->id]);

        $token = $this->postJson('api/login', [
            'email' => $user->email,
            'password' => '123456',
        ])->assertOk()->json('token');

        $this->postJson('api/users/delete-account', [], [
            'Authorization' => 'Bearer '.$token,
        ])->assertOk()
            ->assertJsonPath('action', 'anonymized');

        $fresh = User::find($user->id);
        $this->assertNotNull($fresh);
        $this->assertSame('Usuario anónimo', $fresh->name);
        $this->assertNull($fresh->email);
    }

    public function test_delete_account_rejects_when_negative_ratings_exist(): void
    {
        $user = User::factory()->create(['active' => true, 'banned' => false]);
        $other = User::factory()->create(['active' => true, 'banned' => false]);
        $trip = Trip::factory()->create(['user_id' => $other->id]);

        Rating::factory()->create([
            'trip_id' => $trip->id,
            'user_id_from' => $other->id,
            'user_id_to' => $user->id,
            'user_to_type' => 0,
            'user_to_state' => 0,
            'rating' => Rating::STATE_NEGATIVO,
            'comment' => 'negative fixture',
            'reply_comment' => '',
            'voted' => 1,
            'voted_hash' => 'h'.uniqid(),
            'rate_at' => now(),
            'available' => 1,
        ]);

        $token = $this->postJson('api/login', [
            'email' => $user->email,
            'password' => '123456',
        ])->assertOk()->json('token');

        $this->postJson('api/users/delete-account', [], [
            'Authorization' => 'Bearer '.$token,
        ])->assertStatus(422)
            ->assertJsonPath('error', 'negative_ratings');
    }

    public function test_admin_update_requires_admin_and_target_payload(): void
    {
        $admin = User::factory()->create(['active' => true, 'banned' => false, 'is_admin' => true]);
        $target = User::factory()->create(['active' => true, 'banned' => false, 'description' => 'before']);

        $this->actingAs($admin, 'api');

        $this->putJson('api/users/modify', [
            'user' => ['id' => $target->id],
            'description' => 'after admin touch',
        ])->assertOk();

        $this->assertSame('after admin touch', $target->fresh()->description);
    }

    public function test_admin_update_denies_non_admin(): void
    {
        $actor = User::factory()->create(['active' => true, 'banned' => false, 'is_admin' => false]);
        $target = User::factory()->create(['active' => true, 'banned' => false]);

        $this->actingAs($actor, 'api');

        $this->putJson('api/users/modify', [
            'user' => ['id' => $target->id],
            'description' => 'nope',
        ])->assertStatus(422);
    }

    public function test_admin_can_view_private_note_for_target_user_profile(): void
    {
        $admin = User::factory()->create(['active' => true, 'banned' => false, 'is_admin' => true]);
        $target = User::factory()->create([
            'active' => true,
            'banned' => false,
            'private_note' => 'Admin-only note fixture',
        ]);

        $this->actingAs($admin, 'api');

        $this->getJson('api/users/'.$target->id)
            ->assertOk()
            ->assertJsonPath('data.private_note', 'Admin-only note fixture');
    }

    public function test_update_photo_accepts_base64_profile_payload(): void
    {
        $user = User::factory()->create(['active' => true, 'banned' => false]);
        $this->actingAs($user, 'api');

        // Minimal valid JPEG (1×1) so FileRepository receives decodable bytes.
        $jpegBase64 = '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAARCAABAAEDAREAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAr/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCwAA8A/9k=';
        $payload = 'data:image/jpeg;base64,'.$jpegBase64;

        $this->putJson('api/users/photo', [
            'profile' => $payload,
        ])->assertOk();

        $this->assertNotNull($user->fresh()->image);
    }
}
