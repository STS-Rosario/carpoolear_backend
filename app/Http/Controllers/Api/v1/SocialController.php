<?php

namespace STS\Http\Controllers\Api\v1;

use JWTAuth;
use STS\User;
use Illuminate\Http\Request;
use STS\Http\Controllers\Controller;
use STS\Contracts\Logic\User as UserLogic;
use STS\Contracts\Logic\Devices as DeviceLogic;
use Dingo\Api\Exception\StoreResourceFailedException;

class SocialController extends Controller
{
    protected $user;
    protected $userLogic;
    protected $deviceLogic;

    public function __construct(UserLogic $userLogic, DeviceLogic $devices)
    {
        $this->userLogic = $userLogic;
        $this->deviceLogic = $devices;
        $this->middleware('logged', ['except' => ['login']]);
    }

    public function installProvider($provider, $accessToken)
    {
        $provider = ucfirst(strtolower($provider));
        $providerClass = 'STS\Services\Social\\'.$provider.'SocialProvider';

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
            if (! $user) {
                throw new StoreResourceFailedException('Could not create new user.', $socialServices->gerErrors());
            }
            $token = JWTAuth::fromUser($user);
        } catch (\ReflectionException $e) {
            return response()->json(['error' => 'provider not supported'], 401);
        }

        if ($user->banned) {
            throw new UnauthorizedHttpException('User kick');
        }

        // Registro mi devices
        if ($request->has('device_id') && $request->has('device_type')) {
            $data = $request->all();
            $data['session_id'] = $token;
            $this->deviceLogic->register($user, $data);
        }

        return $this->response->withArray(['token' => $token]);
    }

    public function update(Request $request, $provider)
    {
        $user = $this->auth->user();
        $accessToken = $request->get('access_token');
        $this->installProvider($provider, $accessToken);
        try {
            $socialServices = \App::make('\STS\Contracts\Logic\Social');
            $ret = $socialServices->updateProfile($user);
            if (! $ret) {
                throw new StoreResourceFailedException('Could not update user.', $socialServices->gerErrors());
            }

            return response()->json('OK');
        } catch (\ReflectionException $e) {
            throw new BadRequestHttpException('provider not supported');
        }
    }

    public function friends(Request $request, $provider)
    {
        $user = $this->auth->user();
        $accessToken = $request->get('access_token', $accessToken);
        $this->installProvider($provider);
        try {
            $socialServices = \App::make('\STS\Contracts\Logic\Social');
            $ret = $socialServices->makeFriends($user);
            if (! $ret) {
                throw new StoreResourceFailedException('Could not refresh for friends.', $socialServices->gerErrors());
            }

            return response()->json('OK');
        } catch (\ReflectionException $e) {
            throw new BadRequestHttpException('provider not supported');
        }
    }
}
