<?php

namespace Tests\Unit\Notifications;

use STS\Notifications\AcceptPassengerNotification;
use STS\Notifications\AnnouncementNotification;
use STS\Notifications\AutoCancelPassengerRequestIfRequestLimitedNotification;
use STS\Notifications\AutoCancelRequestIfRequestLimitedNotification;
use STS\Notifications\AutoRequestPassengerNotification;
use STS\Notifications\CancelPassengerNotification;
use STS\Notifications\DeleteTripNotification;
use STS\Notifications\DummyNotification;
use STS\Notifications\FriendAcceptNotification;
use STS\Notifications\FriendCancelNotification;
use STS\Notifications\FriendRejectNotification;
use STS\Notifications\FriendRequestNotification;
use STS\Notifications\HourLeftNotification;
use STS\Notifications\NewMessageNotification;
use STS\Notifications\NewMessagePushNotification;
use STS\Notifications\NewUserNotification;
use STS\Notifications\PendingRateNotification;
use STS\Notifications\RejectPassengerNotification;
use STS\Notifications\RequestNotAnswerNotification;
use STS\Notifications\RequestPassengerNotification;
use STS\Notifications\RequestRemainderNotification;
use STS\Notifications\ResetPasswordNotification;
use STS\Notifications\SubscriptionMatchNotification;
use STS\Notifications\SupportTicketReplyNotification;
use STS\Notifications\UpdateTripNotification;
use STS\Services\Notifications\Channels\DatabaseChannel;
use STS\Services\Notifications\Channels\MailChannel;
use STS\Services\Notifications\Channels\PushChannel;
use STS\Services\Notifications\NotificationServices;
use Tests\TestCase;

class NotificationChannelsViaTest extends TestCase
{
    public static function notificationViaProvider(): array
    {
        return [
            'RequestNotAnswerNotification' => [RequestNotAnswerNotification::class, [DatabaseChannel::class, PushChannel::class]],
            'RejectPassengerNotification' => [RejectPassengerNotification::class, [DatabaseChannel::class, MailChannel::class, PushChannel::class]],
            'AnnouncementNotification' => [AnnouncementNotification::class, [DatabaseChannel::class, PushChannel::class]],
            'AutoRequestPassengerNotification' => [AutoRequestPassengerNotification::class, [DatabaseChannel::class, MailChannel::class, PushChannel::class]],
            'SupportTicketReplyNotification' => [SupportTicketReplyNotification::class, [DatabaseChannel::class, PushChannel::class]],
            'FriendAcceptNotification' => [FriendAcceptNotification::class, [DatabaseChannel::class, MailChannel::class, PushChannel::class]],
            'CancelPassengerNotification' => [CancelPassengerNotification::class, [DatabaseChannel::class, MailChannel::class, PushChannel::class]],
            'FriendRejectNotification' => [FriendRejectNotification::class, [DatabaseChannel::class, MailChannel::class, PushChannel::class]],
            'SubscriptionMatchNotification' => [SubscriptionMatchNotification::class, [DatabaseChannel::class, MailChannel::class, PushChannel::class]],
            'HourLeftNotification' => [HourLeftNotification::class, [DatabaseChannel::class, MailChannel::class, PushChannel::class]],
            'NewMessagePushNotification' => [NewMessagePushNotification::class, [PushChannel::class, DatabaseChannel::class]],
            'UpdateTripNotification' => [UpdateTripNotification::class, [DatabaseChannel::class, MailChannel::class, PushChannel::class]],
            'PendingRateNotification' => [PendingRateNotification::class, [DatabaseChannel::class, MailChannel::class, PushChannel::class]],
            'AcceptPassengerNotification' => [AcceptPassengerNotification::class, [DatabaseChannel::class, MailChannel::class, PushChannel::class]],
            'DeleteTripNotification' => [DeleteTripNotification::class, [DatabaseChannel::class, MailChannel::class, PushChannel::class]],
            'NewMessageNotification' => [NewMessageNotification::class, [DatabaseChannel::class, MailChannel::class, PushChannel::class]],
            'NewUserNotification' => [NewUserNotification::class, [MailChannel::class]],
            'ResetPasswordNotification' => [ResetPasswordNotification::class, [MailChannel::class]],
            'DummyNotification' => [DummyNotification::class, [DatabaseChannel::class, MailChannel::class]],
            'AutoCancelRequestIfRequestLimitedNotification' => [AutoCancelRequestIfRequestLimitedNotification::class, [DatabaseChannel::class, MailChannel::class, PushChannel::class]],
            'RequestRemainderNotification' => [RequestRemainderNotification::class, [DatabaseChannel::class, MailChannel::class, PushChannel::class]],
            'RequestPassengerNotification' => [RequestPassengerNotification::class, [DatabaseChannel::class, MailChannel::class, PushChannel::class]],
            'FriendRequestNotification' => [FriendRequestNotification::class, [DatabaseChannel::class, MailChannel::class, PushChannel::class]],
            'FriendCancelNotification' => [FriendCancelNotification::class, [DatabaseChannel::class, MailChannel::class, PushChannel::class]],
            'AutoCancelPassengerRequestIfRequestLimitedNotification' => [AutoCancelPassengerRequestIfRequestLimitedNotification::class, [DatabaseChannel::class, MailChannel::class, PushChannel::class]],
        ];
    }

    /** @dataProvider notificationViaProvider */
    public function test_notification_exposes_expected_channel_list(string $class, array $expectedVia): void
    {
        $this->mock(NotificationServices::class)->shouldIgnoreMissing();

        $notification = new $class;

        $this->assertSame($expectedVia, $notification->getVia(), $class.'::$via must list every delivery channel');
    }

    public function test_new_user_and_reset_password_force_mail_delivery_flag(): void
    {
        $this->mock(NotificationServices::class)->shouldIgnoreMissing();

        $newUser = new NewUserNotification;
        $this->assertTrue($newUser->force_email);

        $reset = new ResetPasswordNotification;
        $this->assertTrue($reset->force_email);
    }
}
