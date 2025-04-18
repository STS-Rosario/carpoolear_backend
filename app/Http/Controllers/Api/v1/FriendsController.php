<?php

namespace STS\Http\Controllers\Api\v1;

use Illuminate\Http\Request;
use STS\Http\Controllers\Controller;
use STS\Http\ExceptionWithErrors;
use STS\Services\Logic\FriendsManager;
use STS\Services\Logic\UsersManager;
use STS\Transformers\ProfileTransformer;

class FriendsController extends Controller
{
    protected $user;

    protected $friends;

    protected $users;

    public function __construct(Request $r, FriendsManager $friends, UsersManager $users)
    {
        $this->middleware('logged');
        $this->friends = $friends;
        $this->users = $users;
    }

    public function request(Request $request, $id)
    {
        $this->user = auth()->user();
        $friend = $this->users->find($id);
        if ($friend) {
            $ret = $this->friends->request($this->user, $friend);
            if ($ret) {
                return response()->json('OK');
            }
        }

        throw new ExceptionWithErrors('Bad request exceptions', $this->friends->getErrors());
    }

    public function accept(Request $request, $id)
    {
        $this->user = auth()->user();
        $friend = $this->users->find($id);
        if ($friend) {
            $ret = $this->friends->accept($this->user, $friend);
            if ($ret) {
                return response()->json('OK');
            }
        }

        throw new ExceptionWithErrors('Bad request exceptions', $this->friends->getErrors());
    }

    public function delete(Request $request, $id)
    {
        $this->user = auth()->user();
        $friend = $this->users->find($id);
        if ($friend) {
            $ret = $this->friends->delete($this->user, $friend);
            if ($ret) {
                return response()->json('OK');
            }
        }

        throw new ExceptionWithErrors('Bad request exceptions', $this->friends->getErrors());
    }

    public function reject(Request $request, $id)
    {
        $this->user = auth()->user();
        $friend = $this->users->find($id);
        if ($friend) {
            $ret = $this->friends->reject($this->user, $friend);
            if ($ret) {
                return response()->json('OK');
            }
        }

        throw new ExceptionWithErrors('Bad request exceptions', $this->friends->getErrors());
    }

    public function index(Request $request)
    {
        $this->user = auth()->user();
        $data = $request->all();
        $users = $this->friends->getFriends($this->user, $data);
        if (isset($data['page_size'])) {
            return $this->paginator($users, new ProfileTransformer($this->user));
        } else {
            return $this->collection($users, new ProfileTransformer($this->user));
        }
    }

    public function pedings(Request $request)
    {
        $this->user = auth()->user();
        $users = $this->friends->getPendings($this->user);

        return $this->collection($users, new ProfileTransformer($this->user));
    }
}
