<?php

namespace STS\Services\Notifications\Channels;

use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use STS\User as UserModel;
use STS\Services\Notifications\Models\DatabaseNotification;
use STS\Services\Notifications\Models\ValueNotification;

class MailChannel 
{  

    public function __construct()
    { 

    }

    public function send($notification, $user)
    {
         $data = $this->getData($notification, $user);
         $data =  array_merge($data, $notification->getAttributes());
         $data["user"] = $user; 

         \Mail::send('email.' . $data["email_view"], $data, function($message) use ($user, $data) { 
            $message->to($user->email, $user->name)->subject($data["title"]);
        });

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
