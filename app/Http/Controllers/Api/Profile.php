<?php

namespace STS\Http\Controllers\Api;
 
use STS\Http\Controllers\Controller; 
use Illuminate\Http\Request; 
use \STS\Contracts\Logic\User as UserLogic;
use JWTAuth;
use Auth;

class Profile extends Controller
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

    public function update(Request $request, UserLogic $manager)
    {
        $user = $manager->update($this->user, $request->all());
        if (!$user) {
            return response()->json($manager()->getErrors(), 400);
        }
        return $user;
    }

    public function updatePhoto(Request $request, UserLogic $manager)
    {
        $user = $manager->updatePhoto($this->user, $request->all());
        if (!$user) {
            return response()->json($manager()->getErrors(), 400);
        }
        return $user;
    }

    public function show(UserLogic $manager, $id = null)
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