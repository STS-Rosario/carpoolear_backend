<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use STS\Models\User;
use STS\Services\UserEditablePropertiesService;
use Tests\TestCase;

class UserEditablePropertiesServiceTest extends TestCase
{
    public function test_send_flagged_property_alert_posts_slack_message_with_app_profile_url(): void
    {
        Http::fake();

        Config::set('services.slack.forbidden_edit_webhook_url', 'https://hooks.slack.example/forbidden');
        Config::set('carpoolear.frontend_url', 'https://carpoolear.com.ar');

        $user = new User;
        $user->id = 523156;

        $service = new UserEditablePropertiesService;
        $service->sendFlaggedPropertyAlert($user, ['banned']);

        $this->assertCount(1, Http::recorded());

        $expectedLink = 'https://carpoolear.com.ar/app/profile/523156';

        Http::assertSent(function ($request) use ($expectedLink) {
            $payload = $request->data();
            if ($payload === []) {
                $payload = json_decode($request->body(), true) ?? [];
            }
            $text = (string) ($payload['text'] ?? '');

            return str_contains($text, $expectedLink)
                && ! str_contains($text, 'https://carpoolear.com.ar/profile/523156');
        });
    }

    public function test_send_flagged_property_alert_skips_http_when_webhook_is_not_configured(): void
    {
        Http::fake();
        Config::set('services.slack.forbidden_edit_webhook_url', null);
        Config::set('carpoolear.frontend_url', 'https://carpoolear.com.ar');

        $user = new User;
        $user->id = 777;

        $service = new UserEditablePropertiesService;
        $service->sendFlaggedPropertyAlert($user, ['is_admin']);

        Http::assertNothingSent();
    }

    public function test_is_property_allowed_respects_forbidden_allowed_and_admin_allowed_lists(): void
    {
        Config::set('carpoolear.user_edit_properties.forbidden', ['is_admin']);
        Config::set('carpoolear.user_edit_properties.allowed', ['name', 'description']);
        Config::set('carpoolear.user_edit_properties.admin_allowed', ['banned']);

        $service = new UserEditablePropertiesService;

        $this->assertFalse($service->isPropertyAllowed('is_admin', false));
        $this->assertTrue($service->isPropertyAllowed('name', false));
        $this->assertFalse($service->isPropertyAllowed('banned', false));
        $this->assertTrue($service->isPropertyAllowed('banned', true));
        $this->assertFalse($service->isPropertyAllowed('unknown_key', true));
    }

    public function test_filter_for_user_keeps_only_editable_keys_by_role(): void
    {
        Config::set('carpoolear.user_edit_properties.forbidden', ['is_admin']);
        Config::set('carpoolear.user_edit_properties.allowed', ['name', 'description']);
        Config::set('carpoolear.user_edit_properties.admin_allowed', ['banned']);

        $service = new UserEditablePropertiesService;
        $payload = [
            'name' => 'New Name',
            'description' => 'New Description',
            'banned' => true,
            'is_admin' => true,
            'foo' => 'bar',
        ];

        $userFiltered = $service->filterForUser($payload, false);
        $this->assertSame([
            'name' => 'New Name',
            'description' => 'New Description',
        ], $userFiltered);

        $adminFiltered = $service->filterForUser($payload, true);
        $this->assertSame([
            'name' => 'New Name',
            'description' => 'New Description',
            'banned' => true,
        ], $adminFiltered);
    }

    public function test_get_blocked_flagged_properties_that_differ_detects_boolean_and_string_changes(): void
    {
        Config::set('carpoolear.user_edit_properties.forbidden', ['is_admin']);
        Config::set('carpoolear.user_edit_properties.flagged', ['banned']);
        Config::set('carpoolear.user_edit_properties.allowed', ['name']);
        Config::set('carpoolear.user_edit_properties.admin_allowed', []);

        $service = new UserEditablePropertiesService;
        $user = User::factory()->make([
            'is_admin' => false,
            'banned' => false,
            'name' => 'Alice',
        ]);

        $requestData = [
            'is_admin' => 'true',
            'banned' => '1',
            'name' => 'Alice',
        ];
        $filteredData = $service->filterForUser($requestData, false);
        $blocked = $service->getBlockedFlaggedPropertiesThatDiffer($user, $requestData, $filteredData, false);

        $this->assertEqualsCanonicalizing(['is_admin', 'banned'], $blocked);
    }

    public function test_get_blocked_flagged_properties_returns_empty_for_admin_or_same_values(): void
    {
        Config::set('carpoolear.user_edit_properties.forbidden', ['is_admin']);
        Config::set('carpoolear.user_edit_properties.flagged', ['banned']);
        Config::set('carpoolear.user_edit_properties.allowed', ['name']);
        Config::set('carpoolear.user_edit_properties.admin_allowed', ['banned']);

        $service = new UserEditablePropertiesService;
        $user = User::factory()->make([
            'is_admin' => false,
            'banned' => false,
            'name' => 'Alice',
        ]);

        $requestData = [
            'is_admin' => 'false',
            'banned' => 'false',
            'name' => 'Alice',
        ];
        $filteredData = $service->filterForUser($requestData, false);
        $this->assertSame([], $service->getBlockedFlaggedPropertiesThatDiffer($user, $requestData, $filteredData, false));

        $adminFiltered = $service->filterForUser($requestData, true);
        $this->assertSame([], $service->getBlockedFlaggedPropertiesThatDiffer($user, $requestData, $adminFiltered, true));
    }

    public function test_get_blocked_flagged_properties_treats_boolean_equivalents_as_same_value(): void
    {
        Config::set('carpoolear.user_edit_properties.forbidden', ['is_admin']);
        Config::set('carpoolear.user_edit_properties.flagged', ['banned']);
        Config::set('carpoolear.user_edit_properties.allowed', ['name']);
        Config::set('carpoolear.user_edit_properties.admin_allowed', []);

        $service = new UserEditablePropertiesService;
        $user = User::factory()->make([
            'is_admin' => false,
            'banned' => false,
            'name' => 'Alice',
        ]);

        $requestData = [
            'is_admin' => '0',
            'banned' => 'false',
            'name' => 'Alice',
        ];
        $filteredData = $service->filterForUser($requestData, false);
        $blocked = $service->getBlockedFlaggedPropertiesThatDiffer($user, $requestData, $filteredData, false);

        $this->assertSame([], $blocked);
    }
}
