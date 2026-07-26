<?php

namespace STS\Services;

use STS\Models\ManualIdentityValidation;
use STS\Models\User;
use STS\Notifications\ManualIdentityValidationPhotosPurgedNotification;

class ManualIdentityValidationPhotosPurgedNotifier
{
    public function notify(User $user, ManualIdentityValidation $validation): void
    {
        $notification = new ManualIdentityValidationPhotosPurgedNotification;
        $notification->setAttribute('request_id', $validation->id);

        try {
            $notification->notify($user);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
