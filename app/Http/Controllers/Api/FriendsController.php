<?php

namespace STS\Http\Controllers\Api;
 
use STS\Http\Controllers\Controller; 
use Illuminate\Http\Request; 
use \STS\Contracts\Logic\User as UserLogic;
use \STS\Contracts\Logic\Friends as FriendsLogic;
use JWTAuth;
use Auth;

class FriendsController extends Controller
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
 
    public function request (Request $request, FriendsLogic $friends, UserLogic $users, $id) 
    {
        $friend = $users->find($id);
        if ($friend) {
            $ret = $friends->request($this->user, $friend);
            if ($ret) {
                return response()->json("OK");    
            }
        }
        return response()->json($friends()->getErrors(), 400);
    }


    public function accept (Request $request, FriendsLogic $friends, UserLogic $users, $id) 
    {
        $friend = $users->find($id);
        if ($friend) {
            $ret = $friends->accept($this->user, $friend);
            if ($ret) {
                return response()->json("OK");    
            }
        }
        return response()->json($friends()->getErrors(), 400);
    }

    public function delete (Request $request, FriendsLogic $friends, UserLogic $users, $id)
    {
        $friend = $users->find($id);
        if ($friend) {
            $ret = $friends->delete($this->user, $friend);
            if ($ret) {
                return response()->json("OK");    
            }
        }
        return response()->json($friends()->getErrors(), 400);
    }

    public function reject (Request $request, FriendsLogic $friends, UserLogic $users, $id) 
    {
        $friend = $users->find($id);
        if ($friend) {
            $ret = $friends->reject($this->user, $friend);
            if ($ret) {
                return response()->json("OK");    
            }
        }
        return response()->json($friends()->getErrors(), 400);
    }

}