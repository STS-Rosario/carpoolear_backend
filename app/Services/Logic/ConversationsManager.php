<?php

namespace STS\Services\Logic;

use STS\Repository\ConversationRepository;
use STS\Repository\MessageRepository;
use STS\Repository\UserRepository;
use STS\Models\User;
use Carbon\Carbon;
use Validator;
use STS\Models\Message;
use STS\Events\MessageSend;
use STS\Models\Passenger;
use STS\Models\Conversation;
use STS\Models\Trip; 
use STS\Services\Logic\UsersManager;

class ConversationsManager extends BaseManager
{
    protected $messageRepository;

    protected $conversationRepository;

    protected $userRepository;

    protected $friendsLogic;

    protected $userManager;

    public function __construct(ConversationRepository $conversationRepository, MessageRepository $messageRepository, UserRepository $userRepo, FriendsManager $friendsLogic, UsersManager $userManager)
    {
        $this->conversationRepository = $conversationRepository;
        $this->messageRepository = $messageRepository;
        $this->userRepository = $userRepo;
        $this->friendsLogic = $friendsLogic;
        $this->userManager = $userManager;
    }

    /* CONVERSATION CREATION */

    private function createConversation($type, $tripId = null)
    {
        $conversation = new Conversation();
        if ($tripId) {
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

    public function findOrCreatePrivateConversation($user1, $user2, $tripId = null)
    {
        $user1ID = is_int($user1) ? $user1 : $user1->id;
        $user2ID = is_int($user2) ? $user2 : $user2->id;
        $conversation = $this->conversationRepository->matchUser($user1ID, $user2ID);
        // \Log::info('$tripId: ' . $tripId);
        $trip = Trip::find($tripId); // Chequeo que el tripId pertenezca a un viaje

        if (!$trip) {
            $tripId = null;
        } else {
            $module_unaswered_message_limit = config('carpoolear.module_unaswered_message_limit', false);
            if ($module_unaswered_message_limit) {
                $allow = $this->userManager->unansweredConversationOrRequestsByTrip($trip);
                if (!$allow) {
                    $this->setErrors(['error' => 'user_has_reach_request_limit']);
                    return;
                }
            }
        }
        if (!$conversation) {
            if ($this->usersCanChat($user1, $user2ID)) {
                $conversation = $this->createConversation(Conversation::TYPE_PRIVATE_CONVERSATION, $tripId);
                $this->conversationRepository->addUser($conversation, $user1ID);
                $this->conversationRepository->addUser($conversation, $user2ID);

                return $conversation;
            }
        } else {
            if ($tripId) {
                $conversation = $this->updateTripId($conversation, $tripId);
            }
        }
        return $conversation;
    }

    private function updateTripId ($conversation, $tripId) {
        return $this->conversationRepository->updateTripId($conversation, $tripId);
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
        // the message starts no notified
        $message->already_notified = 0;
        $this->messageRepository->store($message);

        return $message;
    }

    private function validator(array $data)
    {
        return Validator::make($data, [
            'user_id'               => 'required|integer',
            'text'                  => 'required|string|max:800',
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
                $otherUsers = $conversation->users()->where('user_id', '!=', $user->id)->get()->unique('id');
                foreach ($otherUsers as $to) {
                    event(new MessageSend($user, $to, $newMessage));
                    $this->messageRepository->createMessageReadState($newMessage, $to, false);
                    $this->conversationRepository->changeConversationReadState($conversation, $to, false);
                }
                if (count($conversation->messages) > 0) {
                    $arr = $conversation->messages->toArray();
                    $initiator = $arr[0];
                    // $initiatorUser = User::where('id', $initiator['user_id'])->first();
                    if (!$conversation->processed_for_average_response) {
                        if (count($conversation->messages) > 1) {
                            for ($i = 1; $i < count($arr); $i++) { 
                                $m = $arr[$i];
                                \Log::info($m['user_id'] . ' = ' . $initiator['user_id']);
                                if ($m['user_id'] != $initiator['user_id']) {
                                    $date = Carbon::parse($initiator['created_at']);
                                    $dateLate = Carbon::parse($m['created_at']);
                                    $diff = (int) $date->diffInSeconds($dateLate);
                                    $to = $user;
                                    if (!isset($to->answer_delay_sum) || is_null($to->answer_delay_sum)) {
                                        $to->answer_delay_sum = 0;
                                    }
                                    if (!isset($to->conversation_answered_count) || is_null($to->conversation_answered_count)) {
                                        $to->conversation_answered_count = 0;
                                    }
                                    $to->conversation_answered_count = $to->conversation_answered_count + 1;
                                    $to->answer_delay_sum = $to->answer_delay_sum + $diff;
                                    $to->save();
                                    
                                    $conversation->processed_for_average_response = true;
                                    $conversation->save();
                                    break;
                                }
                            }
                        }
                    }
                }
                if (!$conversation->processed_for_sum_response) {
                    foreach ($otherUsers as $to) {
                        if (!isset($to->conversation_opened_count) || is_null($to->conversation_opened_count)) {
                            $to->conversation_opened_count = 0;
                        }
                        if (!isset($to->conversation_answered_count) || is_null($to->conversation_answered_count)) {
                            $to->conversation_answered_count = 0;
                        }
                        $to->conversation_opened_count = $to->conversation_opened_count + 1;
                        $to->save();
                    }
                    $conversation->processed_for_sum_response = true;
                    $conversation->save();
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
        $conversations = $this->conversationRepository->getConversationsByTrip($trip);
        $destinations = [];
        foreach ($conversations as $conversation) {
            foreach ($conversation->users as $user) {
                if ($user->id != $trip->user->id) {
                    // tengo que validar que no esté aceptado tambien
                    $esperandoRespuestaSolicitud = false;
                    foreach ($trip->passenger as $request) {
                        if ($request->request_state == Passenger::STATE_PENDING && $request->user_id == $user->id) { 
                            $esperandoRespuestaSolicitud = true;
                            break;
                        }
                    }
                    \Log::info('$esperandoRespuestaSolicitud: ' . $user->id . ' / ' . $esperandoRespuestaSolicitud ? 'true' : 'false');
                    if (!in_array($user->id, $destinations) && $esperandoRespuestaSolicitud) {
                        $destinations[] = $user->id;
                    }
                }
            }
        }

        if (count($destinations)) {
            $message = 'Mensaje automático: El viaje con destino a ';
            $message .= $trip->to_town . ' de fecha ' . $trip->trip_date . ' se ha completado.';
            $this->sendToAll($trip->user, $destinations, $message); // $user, $destinations, $message
        }
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
