<?php

namespace STS\Services\Logic;

use Validator;
use STS\User as UserModel;
use STS\Contracts\SocialProvider;
use STS\Contracts\Logic\User as UserLogic;
use STS\Contracts\Logic\Social as SocialLogic;
use STS\Contracts\Repository\Files as FilesRep;
use STS\Contracts\Logic\Friends as FriendsLogic;
use STS\Contracts\Repository\Social as SocialRepo;

class SocialManager extends BaseManager implements SocialLogic
{
    protected $friendsRepo;
    protected $userLogic;
    protected $filesRepo;
    protected $socialRepo;
    protected $provider;
    protected $userData;

    public function __construct(SocialProvider $provider, UserLogic $userRep, FriendsLogic $friendsRepo, FilesRep $files, SocialRepo $social)
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

    public function loginOrCreate()
    {
        $account = $this->getAccounts();
        if ($account) {
            $user = $account->user;
            if (!$user->image && isset($this->userData['image'])) {
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
        $account = $this->getAccounts();
        if ($account && $user->id == $account->user->id) {
            return $this->syncFriends($account->user);
        } else {
            $this->setErrors(['error' => 'No tiene asociado ningun perfil']);
        }
    }

    public function updateProfile(UserModel $user)
    {
        $account = $this->getAccounts();
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

    private function getAccounts()
    {
        $this->userData = $this->provider->getUserData();
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
        $user = $this->userLogic->create($data, true);
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
