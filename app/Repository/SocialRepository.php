<?php

namespace STS\Repository;

use STS\Models\User;
use STS\Models\SocialAccount;

class SocialRepository
{
    protected $provider;

    public function __construct($provider = null)
    {
        if ($provider) {
            $this->setDefaultProvider($provider);
        }
    }

    public function setDefaultProvider($provider)
    {
        $this->provider = $provider;
    }

    public function getProvider($provider)
    {
        return $this->provider;
    }

    public function find($provider_user_id, $provider = null)
    {
        if (is_null($provider)) {
            $provider = $this->provider;
        }
        $account = SocialAccount::whereProvider($provider)
                                    ->whereProviderUserId($provider_user_id)
                                    ->first();

        return $account;
    }

    public function create(User $user, $provider_user_id, $provider = null)
    {
        if (is_null($provider)) {
            $provider = $this->provider;
        }
        $account = new SocialAccount([
            'provider_user_id' => $provider_user_id,
            'provider'         => $provider,
        ]);
        $account->user()->associate($user);
        $account->save();
    }

    public function delete(SocialAccount $account)
    {
        $account->delete();
    }

    public function get(User $user, $provider = null)
    {
        $accounts = $user->accounts();
        if ($provider) {
            $accounts->where('provider', $provider);
        }

        return $accounts->get();
    }
}
