<?php

namespace Tests\Unit\Services\Notifications\Channels;

use STS\Services\Notifications\Channels\FacebookChannel;
use Tests\TestCase;

class FacebookChannelTest extends TestCase
{
    public function test_get_facebook_secret_concatenates_id_and_secret_from_config(): void
    {
        config([
            'social.facebook_app_id' => 'app-id',
            'social.facebook_app_secret' => 'app-secret',
        ]);

        $channel = new FacebookChannel;

        $this->assertSame('app-id|app-secret', $channel->getFacebookSecret());
    }

    public function test_create_url_includes_provider_user_id_secret_and_template(): void
    {
        config([
            'social.facebook_app_id' => 'id-1',
            'social.facebook_app_secret' => 'sec-2',
        ]);
        $account = (object) ['provider_user_id' => 'fb-user-99'];
        $channel = new FacebookChannel;

        $url = $channel->createUrl($account, 'hello-template');

        $this->assertSame(
            'https://graph.facebook.com/v3.3/fb-user-99/notifications?access_token=id-1|sec-2&template=hello-template',
            $url
        );
    }

    public function test_get_data_prefers_to_facebook_then_falls_back_to_to_string(): void
    {
        $channel = new FacebookChannel;
        $user = (object) ['id' => 1];

        $facebookNotification = new class
        {
            public function toFacebook($user, $device)
            {
                return 'from-facebook';
            }
        };
        $this->assertSame('from-facebook', $channel->getData($facebookNotification, $user));

        $pushNotification = new class
        {
            public function toPush($user, $device)
            {
                return ['message' => 'ignored'];
            }

            public function toString($user = null, $device = null)
            {
                return 'from-to-string';
            }
        };
        $this->assertSame('from-to-string', $channel->getData($pushNotification, $user));
    }

    public function test_get_data_throws_when_notification_has_no_supported_method(): void
    {
        $channel = new FacebookChannel;
        $notification = new class {};

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Method toFacebook does't exists");

        $channel->getData($notification, (object) []);
    }
}
