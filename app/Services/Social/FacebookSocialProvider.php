<?php

namespace STS\Services\Social; 

use SammyK\LaravelFacebookSdk\LaravelFacebookSdk;

class FacebookSocialProvider implements SocialProviderInterface {

    protected $facebook;
    protected $token;
    protected $error;

    public function __construct($token) {
        $this->facebook     = new LaravelFacebookSdk();
        $this->token        = $token;
        $this->facebook->setDefaultAccessToken($this->token);
    }

    public function getProviderName() {
        return "facebook";
    }

    public function getUserData() { 
        try {
            $response = $this->facebook->get('/me?fields=id,name,email,picture.width(300),');
            $fuser = $response->getGraphUser();
            return [
                'provider_user_id'      => $fuser->getId(),
                'email'                 => $fuser->getEmail(),
                'name'                  => $fuser->getName(),
                'gender'                => $user->getGender(),
                'birthday'              => $fuser->getBirthDay(),
                'banned'                => false,
                'terms_and_conditions'  => false,
                'l_image'               => $fuser->getPicture()->getUrl(),
            ];
        } catch (Facebook\Exceptions\FacebookSDKException $e) {
            $this->error = ($e->getMessage());
            return null;
        }
    }

    public function getUserFriends() { 
        try {
            $response = $this->facebook->get('/me/friends?limit=5000');
            $friends = $response->getGraphEdge();
            $res = [];
            foreach($friends as $friend) {
                $res[] = $friend["id"];
            }
            return $friends;
        } catch (Facebook\Exceptions\FacebookSDKException $e) {
            $this->error = ($e->getMessage());
            return null;
        } 
    }

    public function getError() {
        return $this->error;
    }

}