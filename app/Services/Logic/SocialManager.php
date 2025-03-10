<?php

namespace STS\Services\Logic;

use STS\Repository\FileRepository;
use STS\Repository\SocialRepository;
use Validator;
use STS\Models\User as UserModel;
use STS\Contracts\SocialProvider;  

class SocialManager extends BaseManager
{
    protected $friendsRepo;

    protected $userLogic;

    protected $filesRepo;

    protected $socialRepo;

    protected $provider;

    protected $userData;


    // [TODO] social provider
    public function __construct(SocialProvider $provider, UsersManager $userRep, FriendsManager $friendsRepo, FileRepository $files, SocialRepository $social)
    {
        $this->provider = $provider;
        $this->userLogic = $userRep;
        $this->filesRepo = $files;
        $this->socialRepo = $social;
        $this->socialRepo->setDefaultProvider($provider->getProviderName());
        $this->friendsRepo = $friendsRepo;
    }

    public function validator(array $data, $id = null)
    {
        if ($id) {
            return Validator::make($data, [
                'name'  => 'max:255',
                'email' => 'email|max:255|unique:users,email'.$id,
            ]);
        } else {
            return Validator::make($data, [
                'name'  => 'required|max:255',
                'email' => 'required|email|max:255|unique:users',
            ]);
        }
    }

    public function loginOrCreate($data)
    {
        $account = $this->getAccounts($data);
        if ($account) {
            $user = $account->user;
            if (! $user->image && isset($this->userData['image'])) {
                $img = file_get_contents($this->userData['image']);
                $user->image = $this->filesRepo->createFromData($img, 'jpg', 'image/profile/');
                $user->save();
            }
            // $this->syncFriends($account->user);

            return $account->user;
        } else {
            return $this->create($this->provider_user_id, $this->userData);
        }
    }

    public function makeFriends(UserModel $user)
    {
        $account = $this->getAccounts(null);
        if ($account && $user->id == $account->user->id) {
            return $this->syncFriends($account->user);
        } else {
            $this->setErrors(['error' => 'No tiene asociado ningun perfil']);
        }
    }

    public function updateProfile(UserModel $user)
    {
        $account = $this->getAccounts(null);
        if ($account && $user->id == $account->user->id) {
            if (isset($this->userData['image'])) {
                $img = file_get_contents($this->userData['image']);
                $data['image'] = $this->filesRepo->createFromData($img, 'jpg', 'image/profile/');
            }
            //unset($data['email']);
            $user = $this->userLogic->update($user, $this->userData);
            if (! $user) {
                $this->setErrors($this->userLogic->getErrors());

                return;
            }

            return $user;
        } else {
            $this->setErrors(['error' => 'No tiene asociado ningun perfil']);
        }
    }

    public function linkAccount(UserModel $user)
    {
        $account = $this->getAccounts();
        $userAccounts = $this->socialRepo->get($user, $this->socialRepo->getProvider());
        if (! $account && $userAccounts->count() == 0) {
            $this->socialRepo->create($user, $this->provider_user_id);

            return true;
        } else {
            $this->setErrors(['error' => 'Ya tienes asociado un perfil']);
        }
    }

    private function getAccounts($data)
    {
        $this->userData = $this->provider->getUserData($data);
        $this->provider_user_id = $this->userData['provider_user_id'];
        $account = $this->socialRepo->find($this->provider_user_id);

        return $account;
    }

    private function syncFriends($user)
    {
        $friends = $this->getUserFriends();
        foreach ($friends as $friend) {
            $this->friendsRepo->make($user, $friend);
        }

        return true;
    }

    private function create($provider_user_id, $data)
    {
        unset($data['provider_user_id']);
        $data['password'] = null;
        $data['active'] = true;
        if (isset($data['image'])) {
            $img = file_get_contents($data['image']);
            $data['image'] = $this->filesRepo->createFromData($img, 'jpg', 'image/profile/');
        }
        $user = $this->userLogic->create($data, false, true);
        if (! $user) {
            $this->setErrors($this->userLogic->getErrors());

            return;
        }
        $this->socialRepo->create($user, $provider_user_id);
        $this->syncFriends($user);

        return $user;
    }

    private function getUserFriends()
    {
        $list = [];
        $friends = $this->provider->getUserFriends();
        foreach ($friends as $friend) {
            $account = $this->socialRepo->find($friend);
            if ($account) {
                $friend_user = $this->userLogic->show(null, $account->user_id);
                $list[] = $account->user;
            }
        }

        return $list;
    }
}
