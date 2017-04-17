<?php

namespace STS\Services\Logic;

use STS\User;
use Validator;
use STS\Entities\Message;
use STS\Events\MessageSend;
use STS\Entities\Conversation;
use STS\Contracts\Logic\Conversation as ConversationRepo;
use STS\Contracts\Repository\Messages as MessageRepository;
use STS\Contracts\Repository\Conversations as ConversationRepository;

class ConversationsManager extends BaseManager implements ConversationRepo
{
    protected $messageRepository;
    protected $conversationRepository;

    public function __construct(ConversationRepository $conversationRepository, MessageRepository $messageRepository)
    {
        $this->conversationRepository = $conversationRepository;
        $this->messageRepository = $messageRepository;
    }

    /* CONVERSATION CREATION */

    private function createConversation($type, $tripId = null)
    {
        $conversation = new Conversation();
        if ($type == Conversation::TYPE_TRIP_CONVERSATION) {
            if (is_int($tripId) && $tripId >= 0) {
                if (true) { // TripsManager::exist ( $tripId ) I MUST CHECK THE NAME OF THIS METHOD WITH FERNANDO
                    $conversation->trip_id = $tripId;
                } else {
                    return;
                    /* I NEED TO CATCH THE ERROR THROW BY TripsManager::is_exist (tripId)
                    RETURN ERROR */
                }
            } else {
                /*$this->setErrors ( 'ValidationError: tripId must be a possitive integer');*/
                return;
                /*  I must throw an error: "tripId must be an possitive integer"
                RETURN ERROR */
            }
        }
        $conversation->type = $type;
        $conversation->title = '';
        $this->conversationRepository->store($conversation);

        return $conversation;
    }

    public function createTripConversation($trip_id)
    {
        return $this->createConversation(Conversation::TYPE_TRIP_CONVERSATION, $trip_id);
    }

    public function findOrCreatePrivateConversation(User $user1, User $user2)
    {
        $conversation = $this->conversationRepository->matchUser($user1, $user2);
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

        /* check if conversation exist */
    }

    private function usersCanChat(User $user1, User $user2)
    {
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

    public function getUserConversations(User $user, $pageNumber = null, $pageSize = 20)
    {
        return $this->conversationRepository->getConversationsFromUser($user, $pageNumber, $pageSize);
    }

    public function getConversation(User $user, $conversation_id, $pageNumber = null, $pageSize = 20)
    {
        if ($user->is_admin) {
            $user = null;
        }

        return $this->conversationRepository->getConversationFromId($conversation_id, $user);
    }

    public function getConversationByTrip(User $user, $trip_id)
    {
        if ($user->is_admin) {
            $user = null;
        }

        return $this->conversationRepository->getConversationByTripId($trip_id, $user);
    }

    /* CONVERSATION - USER MANIPULATION */

    public function getUsersFromConversation(User $user, $conversationId)
    {
        //Falta chequear permisos
        $conversation = $this->conversationRepository->getConversationFromId($conversationId);

        return $conversation->users;
    }

    public function addUserToConversation(User $user, $conversationId, $users)
    {
        //Falta chequear permisos -> User puede agregar
        $conversation = $this->getConversation($user, $conversationId);
        if ($conversation != null) {
            if ($conversation->type == Conversation::TYPE_TRIP_CONVERSATION) {
                //* CALL: check if user it's in the trip
            }
            if (is_int($users)) {
                $users = [$users];
            }
            $userArray = [];
            foreach ($users as $userId) {
                $user = User::find($userId);
                if ($user) {
                    $usersArray[] = $user;
                } else {
                    $this->setErrors(['user' => 'user_'.$userId.'_does_not_exist']);

                    return;
                }
            }
            foreach ($usersArray as $user) {
                $this->conversationRepository->addUser($conversation, $user);
            }

            return true;
        } else {
            $this->setErrors(['conversation_id' => 'user_does_not_have_access_to_conversation']);
        }
    }

    public function removeUserFromConversation(User $user, $conversationId, User $userToDelete)
    {
        //Falta chequear permisos -> User puede agregar
        $conversation = $this->getConversation($user, $conversationId);
        if ($conversation != null) {
            $this->conversationRepository->removeUser($conversation, $userToDelete);

            return true;
        } else {
            $this->setErrors(['conversation_id' => 'user_does_not_have_access_to_conversation']);
        }
    }

    /* DELETE CONVERSATION */

    public function delete($conversationId)
    {
        $conversation = $this->conversationRepository->getConversationFromId($conversationId);
        if ($conversation) {
            $this->conversationRepository->delete($conversation);
        } else {
            $this->setErrors(['conversation_id' => 'conversation_does_not_exist']);
        }
    }

    /* MESSAGES LOGIC */

    private function newMessage(array $data)
    {
        $message = (new Message())->fill($data);
        $this->messageRepository->store($message);

        return $message;
    }

    private function validator(array $data)
    {
        return Validator::make($data, [
        'user_id'               => 'required|integer',
        'text'                  => 'required|string|max:500',
        'conversation_id'       => 'required|integer',
        ]);
    }

    public function send(User $user, $conversationId, $message)
    {
        $data = [
        'user_id' => $user->id,
        'text' => $message,
        'conversation_id' => $conversationId,
        ];
        $validator = $this->validator($data);
        if (! $validator->fails()) {
            $conversation = $this->getConversation($user, $conversationId);
            if ($conversation) {
                $newMessage = $this->newMessage($data);
                $otherUsers = $conversation->users()->where('user_id', '!=', $user->id)->get();
                foreach ($otherUsers as $to) {
                    event(new MessageSend($user, $to, $newMessage));
                    $this->messageRepository->createMessageReadState($newMessage, $to, false);
                    $this->conversationRepository->changeConversationReadState($conversation, $to, false);
                }

                return $newMessage;
            } else {
                $this->setErrors(['conversation_id' => 'conversation_does_not_exist']);
            }
        } else {
            $this->setErrors($validator->errors());
        }
    }

    public function getAllMessagesFromConversation($conversation_id, User $user, $read = false, $pageNumber = null, $pageSize = 20)
    {
        return $this->getMessagesFromConversation($conversation_id, $user, $read, false, $pageNumber, $pageSize);
    }

    public function getUnreadMessagesFromConversation($conversation_id, User $user, $read = false)
    {
        return $this->getMessagesFromConversation($conversation_id, $user, $read, true, null, null);
    }

    private function getMessagesFromConversation($conversation_id, User $user, $read, $unreadMessages, $pageNumber = null, $pageSize = null)
    {
        //FALTA CHEQUEAR PERMISOS
        $conversation = $this->getConversation($user, $conversation_id);

        if ($conversation) {
            if ($unreadMessages) {
                $messages = $this->messageRepository->getUnreadMessages($conversation, $user);
                if ($read) {
                    foreach ($messages as $message) {
                        $this->messageRepository->changeMessageReadState($message, $user, true);
                    }
                }
            } else {
                $messages = $this->messageRepository->getMessages($conversation, $pageNumber, $pageSize);
            }

            if ($read) {
                $this->conversationRepository->changeConversationReadState($conversation, $user, true);
            }
        } else {
            $messages = null;
            $this->setErrors(['conversation_id' => 'user_does_not_have_access_to_conversation']);
        }

        return $messages;
    }
}
