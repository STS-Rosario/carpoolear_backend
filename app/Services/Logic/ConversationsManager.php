<?php

namespace STS\Services\Logic; 

use STS\Repository\ConversationRepository;
use STS\Repository\MessageRepository;
use STS\Entities\Message;
use STS\Entities\Conversation;
use STS\Services\Logic\TripsManager;
use STS\User;

class ConversationsManager {

    protected $messageRepository;
    protected $conversationRepository;

    public function __construct() 
    { 
        $this->conversationRepository = new ConversationRepository();
        $this->messageRepository = new MessageRepository();
    } 

    /* CONVERSATION CREATION */

    private function createConversation( $type, $tripId = null ) 
    {
        $conversation = new Conversation();
        if ($type == Conversation::TYPE_TRIP_CONVERSATION) {
            if (is_integer($tripId) && $tripId >= 0) {
                if ( TripsManager::exist ( $tripId ) ) { // I MUST CHECK THE NAME OF THIS METHOD WITH FERNANDO
                    $conversation->trip_id = $tripId;
                } else {
                    return null;
                    /* I NEED TO CATCH THE ERROR THROW BY TripsManager::is_exist (tripId)
                       RETURN ERROR */
                }
            } else {
                /*$this->setErrors ( 'ValidationError: tripId must be a possitive integer');*/
                return null;
                /*  I must throw an error: "tripId must be an possitive integer" 
                    RETURN ERROR */
            }
        }
        $conversation->type = $type;
        $conversation->title = "";
        $this->conversationRepository->store($conversation);
        return $conversation;
    }

    public function createTripConversation($trip_id)
    {
        return $this->createConversation( Conversation::TYPE_TRIP_CONVERSATION, $trip_id);
    }

    public function findOrCreatePrivateConversation(User $user1, User $user2)
    {
        $conversation = $this->conversationRepository->matchUser($user1,$user2);
        if ($conversation) {
            return $conversation;
        } else {
            if ($this->usersCanChat($user1, $user2)) {
                $conversation = $this->createConversation(Conversation::TYPE_PRIVATE_CONVERSATION);
                $this->conversationRepository->addUser($conversation, $user1);
                $this->conversationRepository->addUser($conversation, $user2);
                return $conversation;
            }
        }
        return null;
        /* check if conversation exist */ 
    }

    private function usersCanChat(User $user1, User $user2) {
        /* I must check if these two people can chat 
            they can chat if:
            _they have been travelling together. 
            _they are friends 
            _the user it's an admin'
        */
        if ($user1->is_admin || $user2->is_admin) { //anybody can chat with an admin ???
            return true;
        }
        return false;
    }

    /* CONVERSATION GETTERS */

    public function getUserConversations( User $user)
    {
        $conversation = $this->$conversationRepository->getConversationsFromUser($user);
    }

    public function getConversation( User $user, $conversation_id )
    {
        if ($user->is_admin) {
            $user = null;
        }
        return $this->$conversationRepository->getConversationFromId ( $conversation_id, $user );
    }

    public function getConversationByTrip ( User $user, $trip_id)
    {
        if ($user->is_admin) {
            $user = null;
        }
        return $this->$conversationRepository->getConversationByTripId ( $trip_id, $user );
    }

    /* CONVERSATION - USER MANIPULATION */

    public function addUserToConversation( $conversationId, User $user)
    {
        $conversation = $this->conversationRepository->getConversationFromId( $conversationId );
        if ( $conversation->type == Conversation::TYPE_TRIP_CONVERSATION) {
            /* CALL: check if user it's in the trip */
        }
        $this->conversationRepository->addUser( $conversation, $user );
    }

    public function removeUsertFromConversation( $conversationId, User $user) 
    {
        $conversation = $this->getConversation( $user, $conversationId);
        if ( $conversation != null ) {
            $conversationRepository->removeUser( $conversation, $user );
        }
    }

    /* DELETE CONVERSATION */

    public function delete ( $conversationId) {
        $conversation = $conversationRepository->getConversationFromId( $conversationId );
        $conversationRepository->delete( $conversation);
    }

    /* MESSAGES LOGIC */

    private function newMessage(array $data)
    {
            $message = (new Message())->fill($data);
            $this->messageRepository->store($message);
            return $message;
    }

    public function validator(array $data)
    {
            return Validator::make($data, [
                'user_id'               => 'required|integer',
                'text'                  => 'required|strng|max:500',
                'conversation_id'       => 'required|integer',
            ]);
    } 

    public function send(User $user, $conversationId, $message)
    {
        $data = [
             'user_id' => $user->id,
             'text' => $message,
             'conversation_id' => $conversationId
        ];
        if (validator($data)) {
            $existConversation = $this->conversationRepository->getConversationFromId($conversationId);
            if ($existConversation) {
                return $this->newMessage($data);
            }
        }
        return null;
    }

    public function getMessagesFromConversation( $conversation_id, User $user, $unreadMessages)
    {
        
    }

}
