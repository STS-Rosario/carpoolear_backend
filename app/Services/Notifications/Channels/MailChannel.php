<?php

namespace STS\Services\Notifications\Channels;

class MailChannel
{
    public function __construct()
    {
    }

    public function send($notification, $user)
    {
        // \Log::info('mail channel send');
        if ($user->email) {
            $data = $this->getData($notification, $user);
            $data = array_merge($data, $notification->getAttributes());
            $data['user'] = $user;

            if (! config('mail.enabled')) {
                \Log::info('notification info:');
                // \Log::info($data);

                return;
            } else {
                \Log::info('sending_mail:' . json_encode($data));
            }

            /* \Mail::send('email.'.$data['email_view'], $data, function ($message) use ($user, $data) {
                $message->to($user->email, $user->name)->subject($data['title']);
            }); */

            \Log::info('estoy aca:');
            $html = view('email.'.$data['email_view'], $data)->render();
            ssmtp_send_mail($data['title'], $user->email, $html);
            \Log::info('estoy alla:');
        }
    }

    public function getData($notification, $user)
    {
        if (method_exists($notification, 'toEmail')) {
            return $notification->toEmail($user);
        } else {
            throw new \Exception("Method toEmail does't exists");
        }
    }
}
