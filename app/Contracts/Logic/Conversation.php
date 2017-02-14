<?php

namespace STS\Contracts\Logic; 

use STS\User as UserModel;

interface Conversation {

    public function createTripConversation($trip_id);

    public function findOrCreatePrivateConversation(UserModel $user1, UserModel $user2);

    public function getUserConversations( UserModel $user);

    public function getConversation( UserModel $user, $conversation_id );

    public function getConversationByTrip ( UserModel $user, $trip_id);

    public function addUserToConversation( $conversationId, UserModel $user);

    public function removeUsertFromConversation( $conversationId, UserModel $user);

    public function delete ( $conversationId);

    public function send(UserModel $user, $conversationId, $message);

    public function getMessagesFromConversation( $conversation_id, UserModel $user, $unreadMessages);

}
