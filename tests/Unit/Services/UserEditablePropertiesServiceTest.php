<?php

namespace Tests\Unit\Services;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mockery;
use STS\Models\User;
use STS\Services\UserEditablePropertiesService;
use Tests\TestCase;

class UserEditablePropertiesServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_get_forbidden_properties_uses_config_default_is_admin(): void
    {
        Config::set('carpoolear.user_edit_properties', []);

        $svc = new UserEditablePropertiesService;

        $this->assertSame(['is_admin'], $svc->getForbiddenProperties());
    }

    public function test_is_property_allowed_returns_false_for_forbidden_before_other_lists(): void
    {
        Config::set('carpoolear.user_edit_properties.forbidden', ['is_admin', 'nope_key']);
        Config::set('carpoolear.user_edit_properties.allowed', ['is_admin', 'name']);
        Config::set('carpoolear.user_edit_properties.admin_allowed', ['is_admin']);

        $svc = new UserEditablePropertiesService;

        $this->assertFalse($svc->isPropertyAllowed('is_admin', false));
        $this->assertFalse($svc->isPropertyAllowed('is_admin', true));
    }

    public function test_filter_for_user_skips_all_forbidden_keys_even_when_multiple_present(): void
    {
        Config::set('carpoolear.user_edit_properties.forbidden', ['is_admin', 'banned']);
        Config::set('carpoolear.user_edit_properties.allowed', ['name', 'description']);
        Config::set('carpoolear.user_edit_properties.admin_allowed', []);

        $svc = new UserEditablePropertiesService;
        $out = $svc->filterForUser([
            'is_admin' => 1,
            'banned' => 1,
            'name' => 'OK',
            'description' => 'Bio',
        ], false);

        $this->assertSame(['name' => 'OK', 'description' => 'Bio'], $out);
    }

    public function test_get_blocked_flagged_properties_that_differ_returns_empty_for_admin(): void
    {
        $user = Mockery::mock(User::class);
        $svc = new UserEditablePropertiesService;

        $this->assertSame([], $svc->getBlockedFlaggedPropertiesThatDiffer($user, ['x' => 1], [], true));
    }

    public function test_send_flagged_property_alert_logs_non_successful_slack_response(): void
    {
        Config::set('services.slack.forbidden_edit_webhook_url', 'https://hooks.slack.test/webhook');
        Config::set('carpoolear.frontend_url', 'https://admin.test');

        Http::fake([
            'https://hooks.slack.test/*' => Http::response('rate_limited', 429),
        ]);

        Log::shouldReceive('warning')
            ->once()
            ->with(
                'Slack forbidden edit webhook failed',
                Mockery::on(function ($context): bool {
                    return is_array($context)
                        && ($context['status'] ?? null) === 429
                        && ($context['body'] ?? null) === 'rate_limited';
                })
            );

        $user = User::factory()->make(['id' => 501]);
        $svc = new UserEditablePropertiesService;
        $svc->sendFlaggedPropertyAlert($user, ['is_admin']);

        Http::assertSent(fn (\Illuminate\Http\Client\Request $r): bool => str_contains($r->url(), 'hooks.slack.test'));
    }

    public function test_send_flagged_property_alert_logs_exception_message_on_transport_error(): void
    {
        Config::set('services.slack.forbidden_edit_webhook_url', 'https://hooks.slack.test/webhook');
        Config::set('carpoolear.frontend_url', 'https://admin.test');

        Http::fake(function () {
            throw new \RuntimeException('slack down');
        });

        Log::shouldReceive('warning')
            ->once()
            ->with(
                'Slack forbidden edit webhook failed',
                Mockery::on(function ($context): bool {
                    $this->assertIsArray($context);
                    $this->assertArrayHasKey('error', $context);
                    $this->assertSame('slack down', $context['error']);

                    return true;
                })
            );

        $user = User::factory()->make(['id' => 502]);
        $svc = new UserEditablePropertiesService;
        $svc->sendFlaggedPropertyAlert($user, ['banned']);
    }
}
