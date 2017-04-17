<?php

namespace STS\Http\Controllers\Api\v1;

use JWTAuth;
use STS\User;
use Illuminate\Http\Request;
use STS\Http\Controllers\Controller;
use STS\Contracts\Logic\User as UserLogic;
use STS\Contracts\Logic\Devices as DeviceLogic;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

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
        $data['session_id'] = JWTAuth::getToken();  

        if ($device = $this->deviceLogic->register($user, $data)) {
            return $this->response->withArray(['data' => $device]);
        }

        throw new BadRequestHttpException('Bad request exceptions', $this->deviceLogic->getErrors()); 
    }

    public function update($id, Request $request)
    {
        $user = $this->auth->user(); 
        $data = $request->all();
        $data['session_id'] = JWTAuth::getToken();

        if ($device = $this->deviceLogic->update($user, $id, $data)) {
            return $this->response->withArray(['data' => $device]);
        }

        throw new BadRequestHttpException('Bad request exceptions', $this->deviceLogic->getErrors()); 
    }

    public function delete($id, Request $request)
    {
        //$token = JWTAuth::getToken();
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
