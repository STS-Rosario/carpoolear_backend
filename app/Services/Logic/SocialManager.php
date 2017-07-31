<?php

namespace STS\Services\Logic; 

use STS\Repository\SocialRepository;
use STS\Entities\SocialAccount;
use STS\User; 
use STS\Services\Social\SocialProviderInterface;
use STS\Repository\UserRepository; 

class SocialManager extends BaseManager
{

    protected $userRepo;
    protected $socialRepository;
    protected $provider;

    public function __construct(SocialProviderInterface $provider)
    { 
        $this->provider = $provider;
        $this->userRepo = new UserRepository();
        $this->socialRepository = new SocialRepository($provider->getProviderName());
        
    }

    public function validator(array $data, $id = null)
    {
        if ($id) {
            return Validator::make($data, [
                'name' => 'max:255',
                'email' => 'email|max:255|unique:users,email' . $id          
            ]);
        } else {
            return Validator::make($data, [
                'name' => 'required|max:255',
                'email' => 'required|email|max:255|unique:users'        
            ]);
        }
    }

    public function loginOrCreate() {
        $data = $this->provider->getUserData();
        $provider_user_id = $data["provider_user_id"];

        $account = $this->socialRepository->find($provider_user_id);
        if ($account) {
            return $this->userRepo->show($account->user_id);            
        }  else {
            unset($data["provider_user_id"]);
            return $this->create($data);
        }
    }

    private function create($provider_user_id, $data) { 
        $v = $this->validator($data);
        if ($v->fails()) {
            $this->setErrors($v->errors());
            return null;
        } else { 
            $data['password'] = null;
            $user = $this->userRepo->create($data);
            $this->socialRepository->create($user, $provider_user_id);
            return $user;
        } 
    }

    public function matchUserFriends($user) 
    {
        $friends = $this->provider->getUserFriends();
        foreach ($friends as $friend) {
            $account = $this->socialRepository->find($friend);
            if ($account) {
                $friend_user = $this->userRepo->show($account->user_id); 
                $this->userRepo->addFriend($user, $friend_user);
            }                            
        }
    } 
    
}