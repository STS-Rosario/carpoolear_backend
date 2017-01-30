<?php

namespace STS\Http\Controllers\Api;

use STS\Services\FacebookService;
use SammyK\LaravelFacebookSdk\LaravelFacebookSdk;
use STS\Http\Controllers\Controller;
use STS\Http\Requests;
use Illuminate\Http\Request; 
use STS\Services\Logic\UsersManager;
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


        // [TODO] Falta logica de login!
        $token = JWTAuth::fromUser($user);
        return response()->json(compact('token','user'));

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
        if ($request->has("device_id") || $request->has("device_type")) {
            $d = Devices::where("device_id", $request->get("device_id"))->first();
            if (is_null($d)) {
                $d          = new Device();
            }            
            $d->session_id  = $token;
            $d->device_id   = $request->get("device_id");
            $d->device_type = $request->get("device_type");
            $d->usuario_id  = $user->id;
            if ($request->has("app_version")) {
                $d->app_version = $request->get("app_version");
            } else {
                $d->app_version = 0;
            } 
            $d->save();
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

    public function retoken(Request $request) {
        //$user = \JWTAuth::parseToken()->authenticate();
        $user = null;
        $token = \JWTAuth::getToken();
        $newToken = \JWTAuth::refresh($token);
        $d = Devices::where("session_id", $token)->first();
        if ($d) {
            $user = $d->usuario;
            if ($request->has("app_version")) {
                $d->app_version = $request->get("app_version");
            }
            $d->session_id = $newToken;
            $d->save();
        }
        return response()->json(compact('token','user'));
    }

    public function logoff (Request $request) {
        $token = \JWTAuth::parseToken()->getToken(); 
        Devices::where("session_id", $token)->delete();
        //\JWTAuth::parseToken()->invalidate();
        return response()->json("OK");
    }
}