<?php

namespace STS\Contracts\Logic;

use STS\User as UserModel;

interface Conversation
{
    public function setErrors($errs);

    public function getErrors();

    public function createTripConversation($trip_id);

    public function findOrCreatePrivateConversation($user1, $user2);

    public function getUserConversations(UserModel $user, $pageNumber, $pageSize);

    public function getConversation(UserModel $user, $conversation_id, $pageNumber, $pageSize);

    public function getConversationByTrip(UserModel $user, $trip_id);

    public function getUsersFromConversation(UserModel $user, $conversationId);

    public function addUserToConversation(UserModel $user, $conversationId, $users);

    public function removeUserFromConversation(UserModel $user, $conversationId, UserModel $userToDelete);

    public function delete($conversationId);

    public function send(UserModel $user, $conversationId, $message);

    public function getAllMessagesFromConversation($conversation_id, UserModel $user, $read, $timestamp, $pageSize);

    public function getUnreadMessagesFromConversation($conversation_id, UserModel $user, $read);

    public function usersList($user, $searchText);

    public function getMessagesUnread(UserModel $user, $conversation_id = null, $timestamp = null);

    public function show(UserModel $user, $id);

    public function sendToAll(UserModel $user, $destinations, $message);
}
