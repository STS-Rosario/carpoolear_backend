<?php

namespace STS\Http\Controllers\Api\v1;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use STS\Http\Controllers\Controller;
use Illuminate\Http\Request;
use \STS\Contracts\Logic\User as UserLogic;
use \STS\Contracts\Logic\Friends as FriendsLogic;
use JWTAuth;
use Auth;

class FriendsController extends Controller
{
    protected $user;
    protected $friends;
    protected $users;

    public function __construct(Request $r,  FriendsLogic $friends, UserLogic $users)
    {
        $this->middleware('api.auth');
        $this->user = $this->auth->user();
        $this->friends = $friends;
        $this->users = $users;
    }
 
    public function request(Request $request, $id)
    {
        $friend = $this->users->find($id);
        if ($friend) {
            $ret = $this->friends->request($this->user, $friend);
            if ($ret) {
                return response()->json('OK');
            }
        }
        throw new BadRequestHttpException('Bad request exceptions', $this->friends()->getErrors());
    }


    public function accept(Request $request, $id)
    {
        $friend = $this->users->find($id);
        if ($friend) {
            $ret = $this->friends->accept($this->user, $friend);
            if ($ret) {
                return response()->json('OK');
            }
        }
        throw new BadRequestHttpException('Bad request exceptions', $this->friends()->getErrors());
    }

    public function delete(Request $request, $id)
    {
        $friend = $this->users->find($id);
        if ($friend) {
            $ret = $this->friends->delete($this->user, $friend);
            if ($ret) {
                return response()->json('OK');
            }
        }
        throw new BadRequestHttpException('Bad request exceptions', $this->friends()->getErrors());
    }

    public function reject(Request $request, $id)
    {
        $friend = $this->users->find($id);
        if ($friend) {
            $ret = $this->friends->reject($this->user, $friend);
            if ($ret) {
                return response()->json('OK');
            }
        }
        throw new BadRequestHttpException('Bad request exceptions', $this->friends()->getErrors());
    }

    public function index(Request $request)
    {
        return $this->friends->getFriends($this->user);
    }

    public function pedings(Request $request)
    {
        return $this->friends->getPendings($this->user);
    }

}
