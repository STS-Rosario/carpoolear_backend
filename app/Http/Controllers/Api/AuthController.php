<?php

namespace STS\Http\Controllers\Api;
 
use STS\Http\Controllers\Controller; 
use Illuminate\Http\Request; 
use STS\Services\Logic\UsersManager;
use STS\Services\Logic\DeviceManager;
use STS\User;
use STS\Entities\Device;
use JWTAuth;

class AuthController extends Controller
{
    protected $user;
    public function __construct(Request $r)
    {  
        $this->middleware('jwt.auth', ['except' => ['login', 'registrar', 'facebookLogin', 'retoken']]);
    }

    public function registrar(Request $request, UsersManager $manager) {
        $data = $request->all();
        $user = $manager->create($data);
        if (!$user) {
            return response()->json($manager()->getErrors(), 400);
        }

        return response()->json(compact('user'));

    }

    public function login(Request $request, DeviceManager $devices)
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
        if ($request->has("device_id") && $request->has("device_type")) {
            $devices->register($user, $token, $request->all());
        } 
        return response()->json(compact('token','user'));
    }

    /*
    public function facebookLogin(Request $request,FacebookService $service,LaravelFacebookSdk $fb)
    {
        // credenciales para loguear al usuario
        $accessToken = $request->get("accessToken");
        
        $facebook_user = $service->getFacebookUser($fb,$accessToken);

        $user = $service->createOrGetUser($facebook_user);

        if ($user->banned) {
            return response()->json(['error' => 'user_banned'], 401);
        }

        $token = JWTAuth::fromUser($user);

        $result = $service->getFacebookFriends($fb,$accessToken); 
        $service->matchUserFriends($user,$result);

        // Registro mi devices
        if ($request->has("device_id") || $request->has("device_type")) {
            $d = Devices::where("device_id",$request->get("device_id"))->first();
            if (is_null($d)) {
                $d          = new Device();
            }            
            $d->session_id  = $token;
            $d->device_id   = $request->get("device_id");
            $d->device_type = $request->get("device_type");
            $d->usuario_id  = $user->id;
            $d->save();
        }

        return response()->json(compact('token','user'));
    }
    */ 

    public function retoken(Request $request, DeviceManager $devices) {
        //$user = \JWTAuth::parseToken()->authenticate();
        $user = null;
        $token = \JWTAuth::getToken();
        $newToken = \JWTAuth::refresh($token);

        $d = $devices->updateSession($token, $newToken, $request->get("app_version") );
        if ($d) {
            $user = $d->usuario;
        } 

        return response()->json(compact('token','user'));
    }

    public function logoff (Request $request, DeviceManager $devices) {
        $token = \JWTAuth::parseToken()->getToken(); 
        $devices->deleteBySession($token);  
        return response()->json("OK");
    }
}