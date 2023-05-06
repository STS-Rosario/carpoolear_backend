<?php

namespace STS\Http\Controllers\Api\v1;

use JWTAuth;
use STS\User;
use Illuminate\Http\Request;
use STS\Http\Controllers\Controller;
use STS\Contracts\Logic\User as UserLogic;
use Tymon\JWTAuth\Exceptions\JWTException;
use STS\Contracts\Logic\Devices as DeviceLogic;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Dingo\Api\Exception\UpdateResourceFailedException;
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
        $this->middleware('logged', ['only' => ['logout, retoken, getConfig']]);

        $this->userLogic = $userLogic;
        $this->deviceLogic = $devices;
    }

    private function _getConfig () {

        $config = new \stdClass();
        $config->donation = new \stdClass();
        $config->donation->month_days = config('carpoolear.donation_month_days');
        $config->donation->trips_count = config('carpoolear.donation_trips_count');
        $config->donation->trips_offset = config('carpoolear.donation_trips_offset');
        $config->donation->trips_rated = config('carpoolear.donation_trips_rated');
        $config->donation->ammount_needed = config('carpoolear.donation_ammount_needed');
        $config->banner = new \stdClass();
        $config->banner->url = config('carpoolear.banner_url');
        $config->banner->image = config('carpoolear.banner_image');
        $exclude = [
            'donation_month_days',
            'donation_trips_count',
            'donation_trips_offset',
            'donation_trips_rated',
            'donation_ammount_needed',
            'banner_url',
            'banner_image'
        ];
        $allConfigs = config('carpoolear');
        foreach ($exclude as $key) {
            unset($allConfigs[$key]);
        }
        foreach ($allConfigs as $key => $value) {
            $config->$key = $value;
        }
        return $config;
    }

    public function getConfig () {
        return response()->json($this->_getConfig());
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
            throw new UnauthorizedHttpException(null, 'user_banned');
        }

        if (! $user->active) {
            throw new UnauthorizedHttpException(null, 'user_not_active');
        }

        $config = $this->_getConfig();
        return $this->response->withArray([
            'token' => $token,
            'config' => $config
        ]);
    }

    public function retoken(Request $request)
    {
        try {
            $oldToken = $token = JWTAuth::getToken()->get();
            $user = JWTAuth::authenticate($token);
        } catch (TokenExpiredException $e) {
            try {
                $oldToken = JWTAuth::getToken()->get();
                $token = JWTAuth::refresh($oldToken);
            } catch (JWTException $e) {
                throw new AccessDeniedHttpException('invalid_token');
            }
        } catch (JWTException $e) {
            throw new AccessDeniedHttpException('invalid_token');
        }

        $data = [
            'session_id'  => $token,
        ];
        $config = $this->_getConfig();

         if($request->has('app_version')) {
            $data['app_version'] =$request->get('app_version');
            $device = $this->deviceLogic->updateBySession($oldToken, $data);    
          }

        if (isset($user)) {
            // Validar si está baneado
            $user_to_validate = $this->userLogic->find($user->id);
            if ($user_to_validate->banned) {
                return response()->json('banned', 403);
            } else {
                return $this->response->withArray([
                    'token' => $token,
                    'config' => $config,
                ]);
            }
        }
        
        return $this->response->withArray([
            'token' => $token,
            'config' => $config,
        ]);
    }

    public function logout(Request $request)
    {
        JWTAuth::parseToken()->invalidate();

        return response()->json('OK');
    }

    public function active($activation_token, Request $request)
    {
        $user = $this->userLogic->activeAccount($activation_token);
        if (! $user) {
            throw new BadRequestHttpException('user_not_found');
        }
        $token = JWTAuth::fromUser($user);

        return $this->response->withArray(['token' => $token]);
    }

    public function reset(Request $request)
    {
        \Log::info('resetPassword authController');
        $email = $request->get('email');
        if ($email) {
            $token = $this->userLogic->resetPassword($email);
            if ($token) {
                return $this->response->withArray(['data' => 'ok']);
            } else {
                throw new BadRequestHttpException('User not found');
            }
        } else {
            throw new BadRequestHttpException('E-mail not provided');
        }
    }

    public function changePasswod($token, Request $request)
    {
        $data = $request->all();
        $status = $this->userLogic->changePassword($token, $data);
        if ($status) {
            return $this->response->withArray(['data' => 'ok']);
        } else {
            throw new UpdateResourceFailedException('Could not update user.', $this->userLogic->getErrors());
        }
    }
}
