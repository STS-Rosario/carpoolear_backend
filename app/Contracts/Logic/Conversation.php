<?php

namespace STS\Contracts\Logic; 

use STS\User as UserModel;

interface Conversation {

    function createTripConversation($trip_id);

    function findOrCreatePrivateConversation(UserModel $user1, UserModel $user2);

    function getUserConversations( UserModel $user);

    function getConversation( UserModel $user, $conversation_id );

    function getConversationByTrip ( UserModel $user, $trip_id);

    function addUserToConversation( $conversationId, UserModel $user);

    function removeUsertFromConversation( $conversationId, UserModel $user);

    function delete ( $conversationId);

    function send( UserModel $user, $conversationId, $message);

    function getMessagesFromConversation( $conversation_id, UserModel $user, $unreadMessages);

}
