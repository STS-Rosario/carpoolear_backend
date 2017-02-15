<?php

namespace STS\Http\Controllers\Api\v1;

use STS\Http\Controllers\Controller;
use Illuminate\Http\Request;
use \STS\Contracts\Logic\User as UserLogic;  
use Dingo\Api\Exception\StoreResourceFailedException;
use Dingo\Api\Exception\UpdateResourceFailedException;
use Dingo\Api\Exception\ResourceException;

class UserController extends Controller
{
    protected $userLogic; 

    public function __construct(UserLogic $userLogic)
    {
        $this->middleware('api.auth', ['except' => ['create']]);
        $this->userLogic = $userLogic;
    }

    public function create(Request $request)
    {
        $data = $request->all();
        $user = $this->userLogic->create($data);
        if (!$user) {
            throw new StoreResourceFailedException('Could not create new user.', $this->userLogic->getErrors());            
        }
        return response()->json(compact('user'));
    }

    public function update(Request $request)
    {
        $me = $this->auth->user();
        $profile = $this->userLogic->update($me, $request->all());
        if (!$profile) {
            throw new UpdateResourceFailedException('Could not update user.', $this->userLogic->getErrors());
        }
        return $user;
    }

    public function updatePhoto(Request $request)
    {
        $me = $this->auth->user();
        $profile = $this->userLogic->updatePhoto($me, $request->all());
        if (!$profile) {
            throw new  UpdateResourceFailedException('Could not update user.', $this->userLogic->getErrors());
        }
        return $user;
    }

    public function show($id = null)
    {
        $me = $this->auth->user();
        if (!$id) {
            $id = $me->id;
        }
        $user = $this->userLogic->show($me, $id);
        if (!$user) {
            throw new ResourceException('Users not found.', $this->userLogic->getErrors());
        }
        return response()->json($user);
    }

     
}
