<?php

namespace STS\Http\Controllers\Api\v1;

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
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
class AuthController extends Controller
{
    protected $user;
    protected $userLogic;
    protected $deviceLogic;
    public function __construct(UserLogic $userLogic, DeviceLogic $devices)
    {
        $this->userLogic = $userLogic;
        $this->deviceLogic = $devices;
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

        $user = JWTAuth::authenticate($token); 

        if ($user->banned) {
            throw new UnauthorizedHttpException('user_banned');
        }

        if (!$user->active) {
            throw new UnauthorizedHttpException('user_not_active');
        }

        // Registro mi devices
        if ($request->has('device_id') && $request->has('device_type')) {
            $data = $request->all();
            $data['session_id'] = $token;
            $this->deviceLogic->register($user, $data);
        } 
        return $this->response->withArray(['token' => $token]);
    }

    public function retoken(Request $request)
    { 
        $oldToken = JWTAuth::getToken(); 
        if(!$oldToken){
            throw new BadRequestHttpException('Token not provided');
        }
        try{
            $token = JWTAuth::refresh($oldToken);
        }catch(TokenInvalidException $e){
            throw new AccessDeniedHttpException('The token is invalid');
        }

        $data = [
            'session_id' => $token,
            'app_version' => $request->get('app_version')
        ];
        $device = $this->deviceLogic->updateBySession($oldToken, $data);

        return $this->response->withArray(['token' => $token]);
    }

    public function logout(Request $request)
    {
        $token = JWTAuth::parseToken()->getToken();
        $this->deviceLogic->delete($token);
        return response()->json('OK');
    }

    public function active($activation_token, Request $request)
    {
        $user = $this->userLogic->activeAccount($activation_token);
        if (!$user) {
            throw new ResourceException('invalid_activation_token', $this->userLogic->getErrors());
        }
        $token = JWTAuth::fromUser($user);
        if ($request->has('device_id') && $request->has('device_type')) {
            $data = $request->all();
            $data['session_id'] = $token;
            $this->deviceLogic->register($user, $data);
        }
        return $this->response->withArray(['token' => $token]);
    }

}
