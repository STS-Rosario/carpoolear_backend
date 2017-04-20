<?php

namespace STS\Http\Controllers\Api\v1;

use JWTAuth;
use Illuminate\Http\Request;
use STS\Http\Controllers\Controller;
use STS\Contracts\Logic\User as UserLogic;
use STS\Contracts\Logic\Devices as DeviceLogic;
use Dingo\Api\Exception\StoreResourceFailedException;
use Dingo\Api\Exception\UpdateResourceFailedException;

class DeviceController extends Controller
{
    protected $user;
    protected $userLogic;
    protected $deviceLogic;

    public function __construct(UserLogic $userLogic, DeviceLogic $devices)
    {
        $this->middleware('api.auth');
        $this->userLogic = $userLogic;
        $this->deviceLogic = $devices;
    }

    public function register(Request $request)
    {
        $user = $this->auth->user();
        $data = $request->all();
        $data['session_id'] = JWTAuth::getToken()->get();

        if ($device = $this->deviceLogic->register($user, $data)) {
            return $this->response->withArray(['data' => $device]);
        }

        throw new StoreResourceFailedException('Bad request exceptions', $this->deviceLogic->getErrors());
    }

    public function update($id, Request $request)
    {
        $user = $this->auth->user();
        $data = $request->all();
        $data['session_id'] = JWTAuth::getToken()->get();

        if ($device = $this->deviceLogic->update($user, $id, $data)) {
            return $this->response->withArray(['data' => $device]);
        }

        throw new UpdateResourceFailedException('Bad request exceptions', $this->deviceLogic->getErrors());
    }

    public function delete($id, Request $request)
    {
        $user = $this->auth->user();
        $this->deviceLogic->delete($user, $id);

        return response()->json('OK');
    }

    public function index(Request $request)
    {
        $user = $this->auth->user();

        return $this->deviceLogic->getDevices($user);
    }
}
