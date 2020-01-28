<?php

namespace STS\Services\Logic;

use STS\User;
use Carbon\Carbon;
use Validator;
use STS\Entities\Message;
use STS\Events\MessageSend;
use STS\Entities\Conversation;
use STS\Contracts\Logic\Friends as FriendsLogic;
use STS\Contracts\Repository\User as UserRepository;
use STS\Contracts\Logic\Conversation as ConversationRepo;
use STS\Contracts\Repository\Messages as MessageRepository;
use STS\Contracts\Repository\Conversations as ConversationRepository;

class ConversationsManager extends BaseManager implements ConversationRepo
{
    protected $messageRepository;

    protected $conversationRepository;

    protected $userRepository;

    protected $friendsLogic;

    public function __construct(ConversationRepository $conversationRepository, MessageRepository $messageRepository, UserRepository $userRepo, FriendsLogic $friendsLogic)
    {
        $this->conversationRepository = $conversationRepository;
        $this->messageRepository = $messageRepository;
        $this->userRepository = $userRepo;
        $this->friendsLogic = $friendsLogic;
    }

    /* CONVERSATION CREATION */

    private function createConversation($type, $tripId = null)
    {
        $conversation = new Conversation();
        if ($type == Conversation::TYPE_TRIP_CONVERSATION) {
            $conversation->trip_id = $tripId;
        }

        $conversation->type = $type;
        $conversation->title = '';
        $this->conversationRepository->store($conversation);

        return $conversation;
    }

    /**
     *  trip_id always come from system.
     **/
    public function createTripConversation($trip_id)
    {
        return $this->createConversation(Conversation::TYPE_TRIP_CONVERSATION, $trip_id);
    }

    public function findOrCreatePrivateConversation($user1, $user2)
    {
        $user1ID = is_int($user1) ? $user1 : $user1->id;
        $user2ID = is_int($user2) ? $user2 : $user2->id;
        $conversation = $this->conversationRepository->matchUser($user1ID, $user2ID);
        if ($conversation) {
            return $conversation;
        } else {
            if ($this->usersCanChat($user1, $user2ID)) {
                $conversation = $this->createConversation(Conversation::TYPE_PRIVATE_CONVERSATION);
                $this->conversationRepository->addUser($conversation, $user1ID);
                $this->conversationRepository->addUser($conversation, $user2ID);

                return $conversation;
            }
        }
    }

    public function show(User $user, $id)
    {
        return $this->conversationRepository->getConversationFromId($id, $user);
    }

    private function usersCanChat($user1, $user2)
    {
        $user1ID = is_int($user1) ? $user1 : $user1->id;
        $user2ID = is_int($user2) ? $user2 : $user2->id;

        return $user1->is_admin || $this->conversationRepository->usersToChat($user1ID, $user2ID)->count() > 0;
    }

    public function usersList($user, $searchText)
    {
        return $this->conversationRepository->userList($user, null, $searchText);
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

    private function checkPrivateConversation($user, $id)
    {
        $conversation = $this->getConversation($user, $id);
        if ($conversation != null) {
            if ($conversation->type == Conversation::TYPE_TRIP_CONVERSATION) {
                /* This method is used for private conversation only */
                $this->setErrors(['error' => 'access_denied']);

                return;
            }

            return $conversation;
        }
    }

    public function addUserToConversation(User $user, $conversationId, $users)
    {
        if ($conversation = $this->checkPrivateConversation($user, $conversationId)) {
            $users = match_array($users);
            $userArray = [];
            foreach ($users as $userId) {
                $to = $this->userRepository->show($userId);
                if ($to && $this->usersCanChat($user, $userId)) {
                    $usersArray[] = $userId;
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
        if ($conversation = $this->checkPrivateConversation($user, $conversationId)) {
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
                if (!$conversation->processed_for_average_response) {
                    if (countt($conversation->messages) > 2) {
                        $initiator = $conversation->messages[0];
                        for ($i = 1; $i < count($conversation->messages); $i++) { 
                            $m = $conversation->messages[i];
                            if ($m->user->id != $initiator->user->id) {
                                // TODO que hacemos cuando nunca se contesta?
                                // tarea programada
                                $date = Carbon::parse($initiator->created_at);
                                $dateLate = Carbon::now($m->created_at);
                                $diff = $date->diffInSeconds($now);
                                $initiator->user->conversation_opened_count = $initiator->user->conversation_opened_count + 1;
                                $initiator->user->answer_delay_sum = $initiator->user->answer_delay_sum + $diff;
                                break;
                            }
                        }
                        $conversation->processed_for_average_response = true;
                        $conversation->save();
                    }
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

    private function getMessagesFromConversation($conversation_id, User $user, $read, $unreadMessages, $timestamp = null, $pageSize = null)
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
                $messages = $this->messageRepository->getMessages($conversation, $timestamp, $pageSize);
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

    public function sendFullTripMessage (Trip $trip) {
        // obtener todas las personas que consultaron
        $destinations = [];
        $message = 'Mensaje automático: El viaje con destino a ';
        $message .= $trip->to_town . ' de fecha ' . $trip->trip_date . ' se ha completado.';
        $this->sendToAll($trip->user, $destinations, $message); // $user, $destinations, $message
    }

    public function getMessagesUnread(User $user, $conversation_id = null, $timestamp = null)
    {
        $messages = $this->messageRepository->getMessagesUnread($user, $timestamp);

        if ($conversation_id && $conv = $this->conversationRepository->getConversationFromId($conversation_id, $user)) {
            $this->conversationRepository->changeConversationReadState($conv, $user, true);
            $this->messageRepository->markMessages($user, $conv->id);
        }

        return $messages;
        /* if ($conversation_id && $conv = $this->conversationRepository->getConversationFromId($conversation_id, $user)) {
            $this->conversationRepository->changeConversationReadState($conv, $user, true);
            $this->messageRepository->markMessages($user, $conv->id);
        }

        return collect([]);;*/
    }

    public function sendToAll(User $user, $destinations, $message)
    {
        foreach ($destinations as $to) {
            $conver = $this->findOrCreatePrivateConversation($user, $to);
            if ($conver) {
                $m = $this->send($user, $conver->id, $message);
            }
        }

        return true;
    }
}
