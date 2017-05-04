<?php

namespace STS\Http\Controllers\Api\v1;

use Illuminate\Http\Request;
use STS\Http\Controllers\Controller;
use STS\Transformers\ProfileTransformer;
use STS\Contracts\Logic\User as UserLogic;
use STS\Transformers\ConversationsTransformer;
use STS\Contracts\Logic\Conversation as ConversationLogic;
use Dingo\Api\Exception\StoreResourceFailedException as Exception;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class ConversationController extends Controller
{
    protected $user;
    protected $conversations;
    protected $users;

    public function __construct(Request $r, ConversationLogic $conversations, UserLogic $users)
    {
        $this->middleware('api.auth');
        $this->user = $this->auth->user();
        $this->conversationLogic = $conversations;
        $this->users = $users;
    }

    public function index(Request $request)
    {
        $pageNumber = $pageSize = null;
        if ($request->has('page_number')) {
            $pageNumber = $request->get('page_number');
        }
        if ($request->has('page_size')) {
            $pageSize = $request->get('page_size');
        }

        $conversations = $this->conversationLogic->getUserConversations($this->user, $pageNumber, $pageSize);
        if ($conversations) {
            return $this->response->paginator($conversations, new ConversationsTransformer);
        } else {
            throw new Exception('Bad request exceptions', $this->conversationLogic->getErrors());
        }
    }

    public function create(Request $request)
    {
        $to = $request->get('to');
        if ($to) {
            $destinatary = $this->users->find($to);
            if ($destinatary) {
                $conversation = $this->conversationLogic->findOrCreatePrivateConversation($this->user, $destinatary);
                if ($conversation) {
                    return $conversation;
                }
            } else {
                throw new BadRequestHttpException("Bad request exceptions: Destinatary user doesn't exist.");
            }
        } else {
            throw new BadRequestHttpException('Bad request exceptions: Destinatary user not provided.');
        }
        throw new Exception('Bad request exceptions', $this->conversationLogic()->getErrors());
    }

    public function getConversation(Request $request, $id)
    {
        $read = $request->get('read');
        $pageNumber = $request->get('pageNumber');
        $pageSize = $request->get('pageSize');
        $read = $request->get('read');
        $unread = $request->get('unread');
        if ($unread) {
            $messages = $this->conversationLogic->getUnreadMessagesFromConversation($id, $this->user, $read);
        } else {
            $messages = $this->conversationLogic->getAllMessagesFromConversation($id, $this->user, $read, $pageNumber, $pageSize);
        }
        if ($messages) {
            return $messages;
        }
        throw new Exception('Bad request exceptions', $this->conversationLogic->getErrors());
    }

    public function send(Request $request, $id)
    {
        $message = $request->get('message');
        if ($m = $this->conversationLogic->send($this->user, $id, $message)) {
            return $m;
        }
        throw new Exception('Bad request exceptions', $this->conversationLogic->getErrors());
    }

    public function users(Request $request, $id)
    {
        $users = $this->conversationLogic->getUsersFromConversation($this->user, $id);
        if ($users) {
            return $users;
        } else {
            throw new Exception('Bad request exceptions', $this->conversationLogic->getErrors());
        }
    }

    public function addUser(Request $request, $id)
    {
        $users = $request->get('users');
        $ret = $this->conversationLogic->addUserToConversation($this->user, $id, $users);
        if ($ret) {
            return response()->json('OK');
        } else {
            throw new Exception('Bad request exceptions', $this->conversationLogic->getErrors());
        }
    }

    public function deleteUser(Request $request, $id, $userId)
    {
        $userToDelete = $this->users->find($userId);
        $ret = $this->conversationLogic->removeUserFromConversation($this->user, $id, $userToDelete);
        if ($ret) {
            return response()->json('OK');
        } else {
            throw new Exception('Bad request exceptions', $this->conversationLogic->getErrors());
        }
    }

    public function userList(Request $request)
    {
        $search_text = null;
        if ($request->has('value')) {
            $search_text = $request->get('value');
        }
        $users = $this->conversationLogic->usersList($this->user, null, $search_text);

        return $this->collection($users, new ProfileTransformer($this->user));
    }
}
