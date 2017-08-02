<?php

namespace STS\Services\Notifications\Channels;
use GuzzleHttp\Client;

class FacebookChannel
{
    public function __construct()
    {
    }

    public function send($notification, $user)
    {
        $accout = $user->accounts()->where('provider','facebook')->first(); // provider_user_id
        if ($user->account) {
            $data = $this->getData($notification, $user);
            $data = array_merge($data, $notification->getAttributes());
            $data['user'] = $user;
            $message = $data['message'];

            $client = new Client();
            $url = 'https://graph.facebook.com/v2.7/'. $accout->provider_user_id.'/notifications?access_token=.';
            $url .= config('social.facebook_app_token');
            $url .= '&template=' .$message;

            $res = $client->request('POST', $url,[]);
            console_log($res->getStatusCode());
            
        }
    }

    public function getData($notification, $user)
    {
        if (method_exists($notification, 'toFacebook')) {
            return $notification->toFacebook($user, null);
        } else if (method_exists($notification, 'toPush')) {
            return $notification->toPush($user, null);
        } else {
            throw new \Exception("Method toFacebook does't exists");
        }
    }
}
