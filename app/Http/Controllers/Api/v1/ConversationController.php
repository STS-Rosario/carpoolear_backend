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
        
        return $this->response->paginator($conversations, new ConversationsTransformer);
    }

}
