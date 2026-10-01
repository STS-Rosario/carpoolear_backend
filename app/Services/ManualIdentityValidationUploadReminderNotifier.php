<?php

namespace STS\Services;

use STS\Models\ManualIdentityValidation;
use STS\Models\User;
use STS\Notifications\ManualIdentityValidationUploadReminderNotification;

class ManualIdentityValidationUploadReminderNotifier
{
    public function notify(User $user, ManualIdentityValidation $validation): void
    {
        $notification = new ManualIdentityValidationUploadReminderNotification;
        $notification->setAttribute('request_id', $validation->id);

        try {
            $notification->notify($user);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
