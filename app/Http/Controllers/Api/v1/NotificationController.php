<?php

namespace STS\Http\Controllers\Api\v1;

use Illuminate\Http\Request;
use STS\Http\Controllers\Controller;
use STS\Http\ExceptionWithErrors;
use STS\Services\Logic\NotificationManager; 

class NotificationController extends Controller
{
    protected $user;

    protected $logic;

    public function __construct(Request $r, NotificationManager $logic)
    {
        $this->middleware('logged');
        $this->logic = $logic;
    }

    public function index(Request $request)
    {
        $this->user = auth()->user();
        $data = $request->all();
        $notifications = $this->logic->getNotifications($this->user, $data);

        return response()->json(['data' => $notifications]);
    }

    public function count(Request $request)
    {
        $this->user = auth()->user();
        $data = $request->all();
        $count = $this->logic->getUnreadCount($this->user);

        return response()->json(['data' => $count]);
    }

    public function delete($id, Request $request)
    {
        $this->user = auth()->user();
        $result = $this->logic->delete($this->user, $id);
        if (! $result) {
            throw new ExceptionWithErrors('Could not delete notiication.', []);
        }

        return response()->json(['data' => 'ok']);
    }
}
