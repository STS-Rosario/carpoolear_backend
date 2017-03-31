<?php

namespace STS\Contracts\Logic;

use STS\User as UserModel;

interface Conversation {
    
    function setErrors($errs);
    
    function getErrors();
    
    function createTripConversation($trip_id);
    
    function findOrCreatePrivateConversation(UserModel $user1, UserModel $user2);
    
    function getUserConversations( UserModel $user, $pageNumber, $pageSize);
    
    function getConversation( UserModel $user, $conversation_id, $pageNumber, $pageSize );
    
    function getConversationByTrip ( UserModel $user, $trip_id);
    
    function getUsersFromConversation( UserModel $user, $conversationId);
    
    function addUserToConversation( UserModel $user, $conversationId, $users);
    
    function removeUsertFromConversation( UserModel $user, $conversationId, UserModel $userToDelete);
    
    function delete ( $conversationId);
    
    function send( UserModel $user, $conversationId, $message);
    
    function getAllMessagesFromConversation( $conversation_id, UserModel $user, $read, $pageNumber, $pageSize);
    
    function getUnreadMessagesFromConversation( $conversation_id, UserModel $user, $read);
}