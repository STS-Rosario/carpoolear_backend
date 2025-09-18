<?php

namespace STS\Http\Controllers\Api\v1;
 
use Illuminate\Http\Request;
use STS\Http\Controllers\Controller; 
use STS\Http\ExceptionWithErrors;
use STS\Services\Logic\DeviceManager;
use STS\Services\Logic\UsersManager;
use Tymon\JWTAuth\Facades\JWTAuth;

class DeviceController extends Controller
{
    protected $user;

    protected $userLogic;

    protected $deviceLogic;

    public function __construct(UsersManager $userLogic, DeviceManager $devices)
    {
        $this->middleware('logged');
        $this->userLogic = $userLogic;
        $this->deviceLogic = $devices;
    }

    public function register(Request $request)
    {
        $user = auth()->user();
        $data = $request->all();
        $data['session_id'] = JWTAuth::getToken()->get();

        if ($device = $this->deviceLogic->register($user, $data)) {
            return response()->json(['data' => $device]);
        }

        throw new ExceptionWithErrors('Bad request exceptions', $this->deviceLogic->getErrors());
    }

    public function update($id, Request $request)
    {
        $user = auth()->user();
        $data = $request->all();
        $data['session_id'] = JWTAuth::getToken()->get();

        if ($device = $this->deviceLogic->update($user, $id, $data)) {
            return response()->json(['data' => $device]);
        }

        throw new ExceptionWithErrors('Bad request exceptions', $this->deviceLogic->getErrors());
    }

    public function delete($id, Request $request)
    {
        $user = auth()->user();
        $this->deviceLogic->delete($user, $id);

        return response()->json('OK');
    }

    public function index(Request $request)
    {
        $user = auth()->user();

        return response()->json([
            'data' => $this->deviceLogic->getDevices($user),
            'count' => $this->deviceLogic->getActiveDevicesCount($user)
        ]);
    }

    public function logout(Request $request)
    {
        $user = auth()->user();
        $session_id = JWTAuth::getToken()->get();
        
        if ($this->deviceLogic->logoutDevice($session_id, $user)) {
            return response()->json(['message' => 'Device logged out successfully']);
        }
        
        throw new ExceptionWithErrors('Device not found', ['device_not_found']);
    }
}
