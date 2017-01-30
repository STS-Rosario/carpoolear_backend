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
use Auth;

class AuthController extends Controller
{
    protected $user;
    public function __construct(Request $r)
    { 
        $this->middleware('jwt.auth');
        try {
            $this->user = \JWTAuth::parseToken()->authenticate();
        } catch (JWTException $e) {

        }   
    }

    public function update(Request $request, UsersManager $manager)
    {
        $user = $manager->update($this->user, $request->all());
        if (!$user) {
            return response()->json($manager()->getErrors(), 400);
        }
        return $user;
    }

    public function show($id = null , UsersManager $manager)
    {
        if (!$id) {
            $id = $this->user;
        }
        $user = $manager->show($this->user, $id); 
        if (!$user) {
            return response()->json($manager()->getErrors(), 400);
        }
        return response()->json($user);
    }

}