<?php

namespace STS\Repository;

use STS\Entities\Message;

class MessageRepository {

    public function store (Message $message) {
        return $message->save();
    }

    public function delete (Message $message) {
        return $message->delete();
    }

    public function getMessages (Conversation $conversation, $pageNumber = null, $pageSize = 20) {
        $conversationMessages = $conversation->messages();
        if ($pageNumber) {
            $conversationMessages->orderBy('created_at','desc')
                                 ->skip(($pageNumber - 1) * $page_size)
                                 ->take($pageSize);
        }
        return $conversationMessages->get();
    }

    


}