<?php

namespace STS\Contracts\Repository;

use STS\Entities\Message;
use STS\User as UserModel;
use STS\Entities\Conversation;

interface Messages
{
    public function store(Message $message);

    public function delete(Message $message);

    public function getMessages(Conversation $conversation, $timestamp, $pageSize);

    public function getUnreadMessages(Conversation $conversation, UserModel $user);

    public function changeMessageReadState(Message $message, UserModel $user, $read_state);

    public function createMessageReadState(Message $message, UserModel $user, $read_state);

    public function getMessagesUnread(UserModel $user, $timestamp);

    public function markMessages(UserModel $user, $conversation_id);
}
