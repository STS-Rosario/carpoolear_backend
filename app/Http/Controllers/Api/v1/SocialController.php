<?php

namespace STS\Http\Controllers\Api\v1;
 
use Illuminate\Http\Request;
use STS\Http\Controllers\Controller;
use STS\Http\ExceptionWithErrors;
use STS\Services\Logic\DeviceManager;
use STS\Services\Logic\UsersManager;
use Tymon\JWTAuth\Facades\JWTAuth;  

class SocialController extends Controller
{
    protected $user;

    protected $userLogic;

    protected $deviceLogic;

    public function __construct(UsersManager $userLogic, DeviceManager $devices)
    {
        $this->userLogic = $userLogic;
        $this->deviceLogic = $devices;
        $this->middleware('logged')->except(['login']);
        $this->middleware('logged.optional')->only('login');
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
            $user = $socialServices->loginOrCreate($request->all());
            if (! $user) {
                throw new ExceptionWithErrors('Could not create new user.', $socialServices->getErrors());
            }
            $token = JWTAuth::fromUser($user);
        } catch (\ReflectionException $e) {
            return response()->json(['error' => 'provider not supported'], 401);
        }

        if ($user->banned) {
            throw new ExceptionWithErrors(null, 'user_banned');
        }

        // Registro mi devices
        /*
        if ($request->has('device_id') && $request->has('device_type')) {
            $data = $request->all();
            $data['session_id'] = $token;
            $this->deviceLogic->register($user, $data);
        }
        */
        return response()->json(['token' => $token]);
    }

    public function update(Request $request, $provider)
    {
        $user = auth()->user();
        $accessToken = $request->get('access_token');
        $this->installProvider($provider, $accessToken);

        try {
            $socialServices = \App::make('\STS\Contracts\Logic\Social');
            $ret = $socialServices->updateProfile($user);
            if (! $ret) {
                throw new ExceptionWithErrors('Could not update user.', $socialServices->gerErrors());
            }

            return response()->json('OK');
        } catch (\ReflectionException $e) {
            throw new ExceptionWithErrors('provider not supported');
        }
    }

    public function friends(Request $request, $provider)
    {
        $user = auth()->user();
        $accessToken = $request->get('access_token');
        $this->installProvider($provider, $accessToken);

        try {
            $socialServices = \App::make('\STS\Contracts\Logic\Social');
            $ret = $socialServices->makeFriends($user);
            if (! $ret) {
                throw new ExceptionWithErrors('Could not refresh for friends.', $socialServices->gerErrors());
            }

            return response()->json('OK');
        } catch (\ReflectionException $e) {
            throw new ExceptionWithErrors('provider not supported');
        }
    }
}
