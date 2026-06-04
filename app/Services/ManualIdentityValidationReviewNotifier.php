<?php

namespace STS\Services;

use STS\Models\User;
use STS\Notifications\ManualIdentityValidationReviewNotification;

class ManualIdentityValidationReviewNotifier
{
    public function notify(User $user, string $action): void
    {
        if (! in_array($action, ['approved', 'rejected'], true)) {
            return;
        }

        $notification = new ManualIdentityValidationReviewNotification;
        $notification->setAttribute('action', $action);
        try {
            $notification->notify($user);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
