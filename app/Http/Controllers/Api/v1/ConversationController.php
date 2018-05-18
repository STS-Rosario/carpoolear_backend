<?php

namespace STS\Http\Controllers\Api\v1;

use Illuminate\Http\Request;
use STS\Http\Controllers\Controller;
use STS\Transformers\MessageTransformer;
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
        $this->middleware('logged');
        $this->conversationLogic = $conversations;
        $this->users = $users;
    }

    public function index(Request $request)
    {
        $this->user = $this->auth->user();
        $pageNumber = 1;
        $pageSize = 20;
        if ($request->has('page')) {
            $pageNumber = $request->get('page');
        }
        if ($request->has('page_size')) {
            $pageSize = $request->get('page_size');
        }

        $conversations = $this->conversationLogic->getUserConversations($this->user, $pageNumber, $pageSize);
        if ($conversations) {
            return $this->response->paginator($conversations, new ConversationsTransformer($this->user));
        } else {
            throw new Exception('Bad request exceptions', $this->conversationLogic->getErrors());
        }
    }

    public function show($id)
    {
        $this->user = $this->auth->user();
        $conversation = $this->conversationLogic->show($this->user, $id);
        if ($conversation) {
            return $this->response->item($conversation, new ConversationsTransformer($this->user));
        } else {
            throw new BadRequestHttpException('Bad request exceptions');
        }
    }

    public function create(Request $request)
    {
        $this->user = $this->auth->user();
        $to = $request->get('to');
        if ($to) {
            $destinatary = $this->users->find($to);
            if ($destinatary) {
                $conversation = $this->conversationLogic->findOrCreatePrivateConversation($this->user, $destinatary);
                if ($conversation) {
                    return $this->item($conversation, new ConversationsTransformer($this->user), ['key' => 'data']);
                } else {
                    throw new Exception('ConversationController: Unabled to create conversation');
                }
                
            } else {
                throw new BadRequestHttpException("Bad request exceptions: Destinatary user doesn't exist.");
            }
        } else {
            throw new BadRequestHttpException('Bad request exceptions: Destinatary user not provided.');
        }
        throw new Exception('ConversationController: Bad request exceptions');
    }

    public function getConversation(Request $request, $id)
    {
        $this->user = $this->auth->user();
        $read = $request->get('read');
        $timestamp = $request->get('timestamp');
        $pageSize = $request->get('pageSize');
        $read = parse_boolean($request->get('read'));
        $unread = parse_boolean($request->get('unread'));
        if ($unread) {
            $messages = $this->conversationLogic->getUnreadMessagesFromConversation($id, $this->user, $read);
        } else {
            $messages = $this->conversationLogic->getAllMessagesFromConversation($id, $this->user, $read, $timestamp, $pageSize);
        }
        if ($messages) {
            return $this->collection($messages, new MessageTransformer($this->user));
        }
        throw new Exception('Bad request exceptions', $this->conversationLogic->getErrors());
    }

    public function send(Request $request, $id)
    {
        $this->user = $this->auth->user();
        $message = $request->get('message');
        if ($m = $this->conversationLogic->send($this->user, $id, $message)) {
            return $this->item($m, new MessageTransformer($this->user));
        }
        throw new Exception('Bad request exceptions', $this->conversationLogic->getErrors());
    }

    public function users(Request $request, $id)
    {
        $this->user = $this->auth->user();
        $users = $this->conversationLogic->getUsersFromConversation($this->user, $id);
        if ($users) {
            return $users;
        } else {
            throw new Exception('Bad request exceptions', $this->conversationLogic->getErrors());
        }
    }

    public function addUser(Request $request, $id)
    {
        $this->user = $this->auth->user();
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
        $this->user = $this->auth->user();
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
        $this->user = $this->auth->user();
        $search_text = null;
        if ($request->has('value')) {
            $search_text = $request->get('value');
        }
        $users = $this->conversationLogic->usersList($this->user, $search_text);

        return $this->collection($users, new ProfileTransformer($this->user));
    }

    public function getMessagesUnread(Request $request)
    {
        $this->user = $this->auth->user();
        $conversation = null;
        $timestamp = null;
        if ($request->has('conversation_id')) {
            $conversation = $request->get('conversation_id');
        }
        if ($request->has('timestamp')) {
            $timestamp = $request->get('timestamp');
        }
        $messages = $this->conversationLogic->getMessagesUnread($this->user, $conversation, $timestamp);

        return $this->collection($messages, new MessageTransformer($this->user));
    }


    public function multiSend(Request $request) {
        $this->user = $this->auth->user();
        $message = $request->get('message');
        $users = $request->get('users');
        if ($m = $this->conversationLogic->sendToAll($this->user, $users, $message)) {
            return ['message' => true];
        }
        throw new Exception('Bad request exceptions', $this->conversationLogic->getErrors());
    }
}
