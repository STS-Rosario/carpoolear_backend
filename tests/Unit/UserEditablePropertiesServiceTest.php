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
}
