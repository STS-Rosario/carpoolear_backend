<?php

namespace STS\Services\Logic; 

use STS\Repository\SocialRepository;
use STS\Entities\SocialAccount;
use STS\User; 
use STS\Services\Social\SocialProviderInterface;
use STS\Repository\DeviceRepository; 

class DeviceManager extends BaseManager
{

    protected $userRepo;
    protected $socialRepository;
    protected $provider;

    public function __construct()
    { 
        $this->repository = new DeviceRepository();   
    }

    public function register($user, $token, $data) {
        $d = $this->repository->getByDeviceId($data["device_id"]);
        
        if (!$d) {
            $d = $this->repository->create(); 
        }
        
        $d->session_id  = $token;    
        $d->device_id   = $data["device_id"];
        $d->device_type = $data["device_type"];
        $d->user_id     = $user->id;      
        $d->app_version = $data["app_version"];
        
        $this->repository->update($d);
    }

    public function create($provider_user_id, $data) { 
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

    public function updateSession($session_id, $newToken, $version) { 
         $d = $this->repository->getBySessionId($session_id);
         if ($d) {
             $d->session_id  = $newToken; 
             $d->app_version  = $version;
             $this->repository->update($d);
         }
         return $d;
    } 

    public function deleteBySession($session_id) { 
         $d = $this->repository->getBySessionId($session_id);
         if ($d) {
             $this->repository->delete($d);
         }
    } 

    
    
}