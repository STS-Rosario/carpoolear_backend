<?php

namespace STS\Services\Notifications\Channels;

use GuzzleHttp\Client;

class FacebookChannel
{
    public function __construct()
    {
    }

    public function getFacebookSecret()
    {
        $id = config('social.facebook_app_id');
        $secret = config('social.facebook_app_secret');

        return $id.'|'.$secret;
    }

    public function createUrl($account, $template)
    {
        $url = 'https://graph.facebook.com/v3.3/'.$account->provider_user_id.'/notifications?access_token=';
        $url .= $this->getFacebookSecret();
        $url .= '&template='.$template;

        return $url;
    }

    public function send($notification, $user)
    {
        $account = $user->accounts()->where('provider', 'facebook')->first(); // provider_user_id
        if ($account) {
            $message = $this->getData($notification, $user);

            $client = new Client();
            $url = $this->createUrl($account, $message);

            $res = $client->request('POST', $url, []);
        }
    }

    public function getData($notification, $user)
    {
        if (method_exists($notification, 'toFacebook')) {
            return $notification->toFacebook($user, null);
        } elseif (method_exists($notification, 'toPush')) {
            return $notification->toString($user, null);
        } else {
            throw new \Exception("Method toFacebook does't exists");
        }
    }
}
