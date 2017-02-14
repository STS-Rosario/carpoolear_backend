<?php

namespace STS\Repository;

use STS\Entities\Message;
use STS\Entities\Conversation;
use STS\Contracts\Repository\Messages as MessageRepo;

class MessageRepository implements MessageRepo {

    public function store (Message $message) {
        return $message->save();
    }

    public function delete (Message $message) {
        return $message->delete();
    }

    public function getMessages (Conversation $conversation, $pageNumber = null, $pageSize = 20) {
        $conversationMessages = $conversation->messages();
        /*if ($pageNumber) {
            $conversationMessages->orderBy('created_at','desc')
                                 ->skip(($pageNumber - 1) * $page_size)
                                 ->take($pageSize);
        }
        return $conversationMessages->get();*/
        if (!$pageNumber) {
            $pageNumber = 1;
        }
        if ($pageSize == null) {
            return $conversation->messages;
        } else {
            \Illuminate\Pagination\Paginator::currentPageResolver(function () use ($pageNumber) {
                return $pageNumber;
            });
            return $conversation->messages()->orderBy('created_at','desc')->paginate($pageSize);
        }
    }
}