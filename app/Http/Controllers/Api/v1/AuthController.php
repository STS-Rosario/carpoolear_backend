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
        $this->middleware('logged')->only(['logout', 'retoken']);

        $this->userLogic = $userLogic;
        $this->deviceLogic = $devices;
    }

    private function _getConfig ($isCordova = false) {

        $config = new \stdClass();
        $config->donation = new \stdClass();
        $config->donation->month_days = config('carpoolear.donation_month_days');
        $config->donation->trips_count = config('carpoolear.donation_trips_count');
        $config->donation->trips_offset = config('carpoolear.donation_trips_offset');
        $config->donation->trips_rated = config('carpoolear.donation_trips_rated');
        $config->donation->ammount_needed = config('carpoolear.donation_ammount_needed');
        $config->banner = new \stdClass();
        $config->banner->url = $isCordova ? config('carpoolear.banner_url_cordova') : config('carpoolear.banner_url');
        $config->banner->url_mobile = $isCordova ? config('carpoolear.banner_url_cordova_mobile') : config('carpoolear.banner_url_mobile');
        $config->banner->image = $isCordova ? config('carpoolear.banner_image_cordova') : config('carpoolear.banner_image');
        $config->banner->image_mobile = $isCordova ? config('carpoolear.banner_image_cordova_mobile') : config('carpoolear.banner_image_mobile');
        $exclude = [
            'donation_month_days',
            'donation_trips_count',
            'donation_trips_offset',
            'donation_trips_rated',
            'donation_ammount_needed',
            'banner_url',
            'banner_image',
            'qr_payment_pos_external_id', // backend only; frontend gets identity_validation_manual_qr_enabled
            'identity_validation_new_users_date', // backend only; frontend gets identity_validation_required_new_users
        ];
        $allConfigs = config('carpoolear');
        \Log::info('Environment Check:', [
            'raw_env' => [
                'MODULE_USER_REQUEST_LIMITED_ENABLED' => env('MODULE_USER_REQUEST_LIMITED_ENABLED'),
                'MODULE_USER_REQUEST_LIMITED_HOURS_RANGE' => env('MODULE_USER_REQUEST_LIMITED_HOURS_RANGE'),
            ],
            'config_values' => config('carpoolear'),
            'app_env' => app()->environment(),
            'env_path' => app()->environmentFilePath(),
        ]);
        foreach ($exclude as $key) {
            unset($allConfigs[$key]);
        }
        foreach ($allConfigs as $key => $value) {
            $config->$key = $value;
        }
        $config->identity_validation_manual_qr_enabled = config('carpoolear.identity_validation_manual_enabled')
            && config('carpoolear.identity_validation_manual_qr_enabled')
            && !empty(config('services.mercadopago.qr_payment_access_token'))
            && !empty(config('carpoolear.qr_payment_pos_external_id'));
        return $config;
    }

    public function getConfig (Request $request) {
        $isCordova = false;
        if (isset($_SERVER['HTTP_SEC_CH_UA'])) {
            $secChUa = $_SERVER['HTTP_SEC_CH_UA'];
            $userAgent = $_SERVER['HTTP_USER_AGENT'];
            
            $user = auth()->user();
            
            // Check if user is authenticated before accessing properties
            if ($user) {
                $user_id = $user->id;
            } else {
                \Log::warning('getConfig called without authenticated user');
            }
            
            if (strpos($secChUa, 'WebView') !== false && strpos($userAgent, 'Instagram') === false) {
                $isCordova = true;
            }
        }
        
        return response()->json($this->_getConfig($isCordova));
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
            throw new UnauthorizedHttpException('', 'user_banned');
        }

        if (! $user->active) {
            throw new UnauthorizedHttpException('', 'user_not_active');
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
        $user = auth()->user();

        // Clean up only the current device to stop push notifications for this session
        if ($user) {
            $token = JWTAuth::getToken();
            if ($token) {
                $this->deviceLogic->logoutDevice($token, $user);
            }
        }
        
        // Invalidate the JWT token using the correct method
        try {
            $token = JWTAuth::getToken();
            if ($token) {
                JWTAuth::invalidate($token);
                \Log::info('JWT token invalidated successfully');
            }
        } catch (\Exception $e) {
            \Log::error('Failed to invalidate JWT token: ' . $e->getMessage());
        }

        return response()->json('OK');
    }

    public function active($activation_token, Request $request)
    {
        $user = $this->userLogic->activeAccount($activation_token);
        if (! $user) {
            throw new ExceptionWithErrors('user_not_found');
        }
        $token = auth('api')->fromUser($user);

        return response()->json(['token' => $token]);
    }

    public function reset(Request $request)
    {
        // Apply rate limiting
        $request->validate([
            'email' => 'required|email'
        ]);

        $email = $request->get('email');
        
        try {
            $token = $this->userLogic->resetPassword($email);
            if ($token) {
                return response()->json(['data' => 'ok']);
            } else {
                // Check if there are specific errors from the user logic
                $errors = $this->userLogic->getErrors();
                if (!empty($errors)) {
                    $errorMessage = is_array($errors) ? implode(', ', $errors) : $errors;
                    throw new ExceptionWithErrors($errorMessage);
                }
                throw new ExceptionWithErrors('User not found');
            }
        } catch (\Exception $e) {
            \Log::error('Password reset error: ' . $e->getMessage());
            
            // Check if it's a rate limiting error
            if (strpos($e->getMessage(), '450') !== false || strpos($e->getMessage(), 'rate') !== false) {
                throw new ExceptionWithErrors('Too many password reset attempts. Please try again later.');
            }
            
            // Check if it's a cooldown error
            if (strpos($e->getMessage(), 'wait') !== false && strpos($e->getMessage(), 'minutes') !== false) {
                throw new ExceptionWithErrors($e->getMessage());
            }
            
            throw $e;
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
