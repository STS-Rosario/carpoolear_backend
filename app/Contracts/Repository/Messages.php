<?php

namespace STS\Contracts\Repository;

use STS\Entities\Message;
use STS\Entities\Conversation;

interface Messages {

    public function store (Message $message);

    public function delete (Message $message);

    public function getMessages (Conversation $conversation, $pageNumber = null, $pageSize = 20);
    
}