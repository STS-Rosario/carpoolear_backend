<?php

namespace STS\Services\Logic; 

use STS\Contracts\Repository\User as UserRep;
use STS\Contracts\Repository\Friends as FriendsRep;
use STS\Contracts\Repository\Files as FilesRep;
use STS\Contracts\Repository\Social as SocialRepo;
use STS\Contracts\Logic\Social as SocialLogic;

use STS\Contracts\SocialProvider;

use STS\Entities\SocialAccount;
use STS\User;  
use Validator;

class SocialManager extends BaseManager implements SocialLogic
{

    protected $friendsRepo;
    protected $userRepo;
    protected $filesRepo;
    protected $socialRepo;
    protected $provider;
    protected $userData;

    public function __construct(SocialProvider $provider, UserRep $userRep, FriendsRep $friendsRepo, FilesRep $files, SocialRepo $social)
    { 
        $this->provider     = $provider;
        $this->userRepo     = $userRep;
        $this->filesRepo    = $files;
        $this->userRepo     = $userRep;
        $this->socialRepo   = $social;        
        $this->socialRepo->setDefaultProvider($provider->getProviderName());
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
        $account = $this->getAccounts();
        if ($account) {
            return $this->userRepo->show($account->user_id);            
        }  else {            
            return $this->create($provider_user_id, $this->userData);
        }
    }

    public function makeFriends(User $user) {
        $account = $this->getAccounts();
        if ($account) {
            return $this->syncFriends($account->user);
        }
        return null;
    }

    public function updateProfile(User $user) {
        $account = $this->getAccounts();
        if ($account) {
             if (isset($data['image'])){
                $img = file_get_contents($data['image']); 
                $data['image'] = $this->filesRepo->createFromData($img, 'jpg', 'image/profile/');
            }
            unset($data['email']);
            $user = $this->userRepo->update($user, $data);
        }
        return null;
    }

    public function linkAccount(UserModel $user) {
        $account = $this->getAccounts();
        if (!$account) {
            $this->socialRepo->create($user, $provider_user_id);     
            return true;
        }
        return null;
    }

    private function getAccounts() {
        $this->userData = $this->provider->getUserData();
        $provider_user_id = $data["provider_user_id"];
        $account = $this->socialRepo->find($provider_user_id);
        return $account;
    }

    private function syncFriends($user) {
        $friends = $this->getUserFriends();
        foreach ($friends as $friend) {
            $this->friendsRepo->make($user, $friend);
        }
        return true;
    }


    private function create($provider_user_id, $data) { 
        unset($data["provider_user_id"]);
        $v = $this->validator($data);
        if ($v->fails()) {
            $this->setErrors($v->errors());
            return null;
        } else { 
            $data['password'] = null;
            if (isset($data['image'])){
                $img = file_get_contents($data['image']); 
                $data['image'] = $this->filesRepo->createFromData($img, 'jpg', 'image/profile/');
            }
            $user = $this->userRepo->create($data);
            $this->socialRepo->create($user, $provider_user_id);
            $this->syncFriends($user);
            return $user;
        } 
    }

    private function getUserFriends() 
    {
        $list = [];
        $friends = $this->provider->getUserFriends();
        foreach ($friends as $friend) {
            $account = $this->socialRepo->find($friend);
            if ($account) {
                $friend_user = $this->userRepo->show($account->user_id); 
                $list[] = $account->user;
            }                            
        }
        return $list;
    } 
    
}
