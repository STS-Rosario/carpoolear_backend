<?php

namespace STS\Http\Controllers\Api;
 
use STS\Http\Controllers\Controller; 
use Illuminate\Http\Request; 
use STS\Services\Logic\UsersManager;
use STS\Services\Logic\DeviceManager;
use STS\Services\Logic\SocialManager;

use STS\Services\Social\FacebookSocialProvider;

use STS\User;
use STS\Entities\Device;
use JWTAuth;


use \GuzzleHttp\Client;


class AuthController extends Controller
{
    protected $user;
    public function __construct(Request $r)
    {  
        $this->middleware('jwt.auth', ['except' => ['login', 'registrar', 'facebook', 'retoken']]);
    }

    public function registrar(Request $request, UsersManager $manager) {
        $data = $request->all();
        $user = $manager->create($data);
        if (!$user) {
            return response()->json($manager()->getErrors(), 400);
        }

        return response()->json(compact('user'));

    }

    public function login($provider = null, Request $request, DeviceManager $devices)
    { 
        if ($provider) {
            if ($provider == "facebook") {
                $accessToken = $request->get("access_token");
                $fb = new FacebookSocialProvider($accessToken);
                $socialServices = new SocialManager($fb); 
                // credenciales para loguear al usuario
                    
                $user = $socialServices->loginOrCreate();
                $token = JWTAuth::fromUser($user);
                //return $socialServices->getUserFriends();
            } else {
                return response()->json(['error' => 'provider not supported'], 401);
            }
        } else { 
            $credentials = $request->only('email', 'password');
            
            try {
                if (! $token = JWTAuth::attempt($credentials)) {
                    return response()->json(['error' => 'invalid_credentials'], 401);
                }
            } catch (JWTException $e) {
                return response()->json(['error' => 'could_not_create_token'], 500);
            }

            $user = \JWTAuth::authenticate($token);
        }

        if ($user->banned) {
            return response()->json(['error' => 'user_banned'], 401);
        }

        // Registro mi devices
        if ($request->has("device_id") && $request->has("device_type")) {
            $devices->register($user, $token, $request->all());
        } 
        return response()->json(compact('token','user'));
    }

     

    //  https://graph.facebook.com/v2.7/me?fields=email,name,gender,picture.width(300),birthday&access_token=EAALyfIRbBbYBADS2SZCk0X7bU20uuXizOqF1njbFfDWAMnF71kWRaV3xSlJlGft3XHzhNhG0UBKZAmiQQigpFVgQLno3LOneY1WDtdQAmPcqg30JZAxJ5goJnprdETUcGbZB1zU4T0Wg6kc5Ye40BfINMwIAzS386RNUbqYzj4M6iX34xZCVt
    public function facebook(Request $request)
    {
        $accessToken = $request->get("access_token");
        $fb = new FacebookSocialProvider($accessToken);
        $socialServices = new SocialManager($fb); 
        // credenciales para loguear al usuario
         
        $u = $socialServices->loginOrCreate();
        return $socialServices->getUserFriends();
    } 

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