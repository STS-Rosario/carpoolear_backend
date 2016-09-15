<?php

namespace STS\Services; 

use STS\Entities\SocialAccount;
use STS\User;
use SammyK\LaravelFacebookSdk\LaravelFacebookSdk;

class FacebookService
{

    public function getFacebookUser($fb,$token)
    {
        $fb->setDefaultAccessToken($token);
        try {
            $response = $fb->get('/me?fields=id,name,email,age_range,locale,picture.width(300)');
        } catch (Facebook\Exceptions\FacebookSDKException $e) {
            die($e->getMessage());
        }
        return $response->getGraphUser();
    }

    public function getFacebookFriends($fb,$token)
    {
        $fb->setDefaultAccessToken($token);
        try {
            $response = $fb->get('/me/friends?limit=5000');
        } catch (Facebook\Exceptions\FacebookSDKException $e) {
            die($e->getMessage());
        }

        return $response->getGraphEdge(); 
    }

    public function matchUserFriends($user,$friends) 
    {
        foreach ($friends as $friend) {
            $account = SocialAccount::whereProvider('facebook')
                                    ->whereProviderUserId($friend["id"])
                                    ->first();
            if ($account) {
                $fuser = $account->user;
 
                $fuser->friends()->detach($user->id);
                $user->friends()->detach($fuser->id);

                $fuser->friends()->attach($user->id);
                $user->friends()->attach($fuser->id);

            }                            
        }
    }

    public function createOrGetUser($fuser)
    {
        $id = $fuser->getId();
        $account = SocialAccount::whereProvider('facebook')
            ->whereProviderUserId($id)
            ->first();

        if ($account) {
            return $account->user;
        } else {

            $account = new SocialAccount([
                'provider_user_id' => $fuser->getId(),
                'provider' => 'facebook'
            ]);

            $user = User::whereEmail($fuser->getEmail())->first();

            if (!$user) {

                $user = User::create([
                    'email'     => $fuser->getEmail(),
                    'name'      => $fuser->getName(),
                    'image'     => $fuser->getPicture()->getUrl(),
                ]);

            }

            $account->user()->associate($user);
            $account->save();

            return $user;

        }

    }
}