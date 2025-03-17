<?php

namespace STS\Http\Controllers\Api\v1;
 
use STS\Http\ExceptionWithErrors;
use STS\Services\Logic\DeviceManager;
use STS\Services\Logic\UsersManager;
use STS\User;
use Illuminate\Http\Request;
use STS\Http\Controllers\Controller; 
use Tymon\JWTAuth\Exceptions\JWTException; 
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    protected $user;

    protected $userLogic;

    protected $deviceLogic;

    public function __construct(UsersManager $userLogic, DeviceManager $devices)
    {
        $this->middleware('logged')->only(['logout', 'retoken', 'getConfig']);

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

        $user = auth()->user();

        if ($user->banned) {
            throw new UnauthorizedHttpException(null, 'user_banned');
        }

        if (! $user->active) {
            throw new UnauthorizedHttpException(null, 'user_not_active');
        }

        $config = $this->_getConfig();
        return response()->json([
            'token' => $token,
            'config' => $config
        ]);
    }

    public function retoken(Request $request)
    {
        try {
            $oldToken = $token = JWTAuth::getToken()->get();
            $payload = JWTAuth::setToken($token)->checkOrFail();
            $user = JWTAuth::setToken($token)->user();
        } catch (TokenExpiredException $e) {
            try {
                $oldToken = auth('api')->getToken()->get();
                $token = auth('api')->refresh($oldToken);
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

        if ($request->has('app_version')) {
            $data['app_version'] =$request->get('app_version');
            $device = $this->deviceLogic->updateBySession($oldToken, $data);    
        }

        if (isset($user)) {
            // Validar si está baneado
            $user_to_validate = $this->userLogic->find($user->id);
            if ($user_to_validate->banned) {
                return response()->json('banned', 403);
            } else {
                return response()->json([
                    'token' => $token,
                    'config' => $config,
                ]);
            }
        }
        
        return response()->json([
            'token' => $token,
            'config' => $config,
        ]);
    }

    public function logout(Request $request)
    {
        auth()->parseToken()->invalidate();

        return response()->json('OK');
    }

    public function active($activation_token, Request $request)
    {
        $user = $this->userLogic->activeAccount($activation_token);
        if (! $user) {
            throw new ExceptionWithErrors('user_not_found');
        }
        $token = auth()->fromUser($user);

        return response()->json(['token' => $token]);
    }

    public function reset(Request $request)
    {
        $email = $request->get('email');
        if ($email) {
            $token = $this->userLogic->resetPassword($email);
            if ($token) {
                return response()->json(['data' => 'ok']);
            } else {
                throw new ExceptionWithErrors('User not found');
            }
        } else {
            throw new ExceptionWithErrors('E-mail not provided');
        }
    }

    public function changePasswod($token, Request $request)
    {
        $data = $request->all();
        $status = $this->userLogic->changePassword($token, $data);
        if ($status) {
            return response()->json(['data' => 'ok']);
        } else {
            throw new ExceptionWithErrors('Could not update user.', $this->userLogic->getErrors());
        }
    }

    public function log() {
        return true;
    }
}
