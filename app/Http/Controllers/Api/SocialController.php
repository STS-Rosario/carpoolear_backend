<?php

namespace STS\Http\Controllers\Api;

use STS\Http\Controllers\Controller;
use Illuminate\Http\Request;
use STS\Services\Logic\DeviceManager;
use STS\Services\Logic\SocialManager;

use STS\Services\Social\FacebookSocialProvider;

use STS\User;
use STS\Entities\Device;
use JWTAuth;

use \GuzzleHttp\Client;
use \STS\Contracts\Logic\User as UserLogic;
use STS\Contracts\Logic\Devices as DeviceLogic;

class SocialController extends Controller
{
    protected $user;
    protected $userLogic;
    protected $deviceLogic;
    public function __construct(UserLogic $userLogic, DeviceLogic $devices)
    {
        $this->userLogic = $userLogic;
        $this->deviceLogic = $devices;
        $this->middleware('jwt.auth', ['except' => ['login']]);
    }
 
    public function installProvider($provider, $accessToken)
    {
        $provider = ucfirst(strtolower($provider));
        $providerClass = 'STS\Services\Social\\' . $provider . 'SocialProvider';

        \App::when($providerClass)
                    ->needs('$token')
                    ->give($accessToken);

        \App::bind('\STS\Contracts\SocialProvider', $providerClass);
    }

    public function login(Request $request, $provider)
    {
        $accessToken = $request->get('access_token');
        $this->installProvider($provider, $accessToken);

        try {
            $socialServices = \App::make('\STS\Contracts\Logic\Social');
            $user = $socialServices->loginOrCreate();
            if (!$user) {
                return response()->json($socialServices->gerErrors(), 401);
            }
            $token = JWTAuth::fromUser($user);
        } catch (\ReflectionException $e) {
            return response()->json(['error' => 'provider not supported'], 401);
        }

        if ($user->banned) {
            return response()->json(['error' => 'user_banned'], 401);
        }

        // Registro mi devices
        if ($request->has('device_id') && $request->has('device_type')) {
            $data = $request->all();
            $data['session_id'] = $token;
            $this->deviceLogic->register($user, $data);
        }
        return response()->json(compact('token', 'user'));
    }

    public function update(Request $request, $provider)
    {
        $user = \JWTAuth::parseToken()->authenticate();
        $accessToken = $request->get('access_token');
        $this->installProvider($provider, $accessToken);
        try {
            $socialServices = \App::make('\STS\Contracts\Logic\Social');
            $user = $socialServices->updateProfile($user);
        } catch (\ReflectionException $e) {
            return response()->json(['error' => 'provider not supported'], 401);
        }
    }

    public function friends(Request $request, $provider)
    {
        $user = \JWTAuth::parseToken()->authenticate();
        $accessToken = $request->get('access_token', $accessToken);
        $this->installProvider($provider);
        try {
            $socialServices = \App::make('\STS\Contracts\Logic\Social');
            $user = $socialServices->makeFriends($user);
        } catch (\ReflectionException $e) {
            return response()->json(['error' => 'provider not supported'], 401);
        }
    }
}
