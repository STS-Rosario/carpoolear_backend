<?php

namespace STS\Http\Controllers\Api;

use STS\Services\FacebookService;
use SammyK\LaravelFacebookSdk\LaravelFacebookSdk;
use STS\Http\Controllers\Controller;
use STS\Http\Requests;
use Illuminate\Http\Request; 
use STS\Repository\UsersManager;
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
        return $manager->update($this->user, $request->all());
    }

    public function show($id,UsersManager $manager)
    {
        $profile = User::find($id);
        $manager->show($this->user,$profile); 
        return response()->json($user);
    }

}