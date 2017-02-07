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

class AuthController extends Controller
{
    protected $user;
    protected $userLogic;
    protected $deviceLogic;
    public function __construct(UserLogic $userLogic, DeviceLogic $devices)
    {
        $this->userLogic = $userLogic;
        $this->deviceLogic = $devices;
        $this->middleware('jwt.auth', ['except' => ['login', 'registrar', 'facebook', 'retoken']]);
    }

    public function registrar(Request $request)
    {
        $data = $request->all();
        $user = $this->userLogic->create($data);
        if (!$user) {
            return response()->json($this->userLogic->getErrors(), 400);
        }

        return response()->json(compact('user'));
    }

    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');
        
        try {
            if (! $token = JWTAuth::attempt($credentials)) {
                return response()->json(['error' => 'invalid_credentials'], 401);
            }
        } catch (JWTException $e) {
            return response()->json(['error' => 'could_not_create_token'], 500);
        }

        $user = \JWTAuth::authenticate($token);
 

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

    public function retoken(Request $request)
    {
        //$user = \JWTAuth::parseToken()->authenticate();
        $user = null;
        $token = \JWTAuth::getToken();
        $newToken = \JWTAuth::refresh($token);

        $data = [
            'session_id' => $newToken,
            'app_version' => $request->get('app_version')
        ];

        $device = $this->deviceLogic->updateBySession($token, $data);
        if ($device) {
            $user = $device->usuario;
        }

        return response()->json(compact('token', 'user'));
    }

    public function logoff(Request $request)
    {
        $token = \JWTAuth::parseToken()->getToken();
        $this->deviceLogic->delete($token);
        return response()->json('OK');
    }
}
