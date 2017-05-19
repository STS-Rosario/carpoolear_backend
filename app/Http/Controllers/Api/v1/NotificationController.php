<?php

namespace STS\Http\Controllers\Api\v1;

use Illuminate\Http\Request;
use STS\Http\Controllers\Controller;
use Dingo\Api\Exception\StoreResourceFailedException;
use STS\Contracts\Logic\INotification as NotificationLogic;

class NotificationController extends Controller
{
    protected $user;
    protected $logic;

    public function __construct(Request $r, NotificationLogic $logic)
    {
        $this->middleware('logged');
        $this->logic = $logic;
    }

    public function index(Request $request)
    {
        $this->user = $this->auth->user();
        $data = $request->all();
        $notifications = $this->logic->getNotifications($this->user, $data);

        return $this->response->withArray(['data' => $notifications]);
    }

    public function count(Request $request)
    {
        $this->user = $this->auth->user();
        $data = $request->all();
        $count = $this->logic->getUnreadCount($this->user);

        return $this->response->withArray(['data' => $count]);
    }

    public function delete($id, Request $request)
    {
        $this->user = $this->auth->user();
        $result = $this->logic->delete($this->user, $id);
        if (! $result) {
            throw new StoreResourceFailedException('Could not delete notiication.', []);
        }

        return $this->response->withArray(['data' => 'ok']);
    }
}
