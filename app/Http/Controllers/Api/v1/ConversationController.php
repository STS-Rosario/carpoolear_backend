<?php

namespace STS\Http\Controllers\Api\v1;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use STS\Http\Controllers\Controller;
use Illuminate\Http\Request;
use \STS\Contracts\Logic\User as UserLogic;
use \STS\Contracts\Logic\Conversation as ConversationLogic;
use JWTAuth;
use Auth;

use STS\Transformers\ConversationsTransformer;


class ConversationController extends Controller
{
    protected $user;
    protected $conversations;
    protected $users;
    
    public function __construct(Request $r,  ConversationLogic $conversations, UserLogic $users)
    {
        $this->user = $this->auth->user();
        $this->conversationLogic = $conversations;
        $this->users = $users;
    }
    
    public function index(Request $request)
    {
        $conversations = $this->conversationLogic->getUserConversations($this->user);
        if ($conversations) {
            return $this->response->paginator($conversations, new ConversationsTransformer);
        } else {
            throw new BadRequestHttpException('Bad request exceptions', $this->conversations()->getErrors());
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
        throw new BadRequestHttpException('Bad request exceptions', $this->conversations()->getErrors());
    }
    
    public function get_conversation(Request $request, $id)
    {
        $read = $request->get('read');
        $pageNumber = $request->get('pageNumber');
        $pageSize = $request->get('pageNumber');
        $messages = $this->conversationLogic->getAllMessagesFromConversation( $id, $this->user, $read, $pageNumber, $pageSize);
        if ($messages) {
            return $messages;
        }
        throw new BadRequestHttpException('Bad request exceptions', $this->conversations()->getErrors());
    }
    
}