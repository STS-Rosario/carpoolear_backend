<?php

namespace STS\Services\Logic;

use Validator;
use STS\Entities\Passenger;
use STS\Contracts\Logic\IPassengersLogic;
use STS\Contracts\Logic\Trip as TripLogic;
use STS\Contracts\Repository\User as UserRepo;
use STS\Events\Passenger\Accept as AcceptEvent;
use STS\Events\Passenger\Cancel as CancelEvent;
use STS\Events\Passenger\Reject as RejectEvent;
use STS\Events\Passenger\Request as RequestEvent;
use STS\Events\Passenger\AutoRequest as AutoRequestEvent;
use STS\Contracts\Repository\IPassengersRepository;

class PassengersManager extends BaseManager implements IPassengersLogic
{
    protected $passengerRepository;

    protected $tripLogic;

    protected $uRepo;

    public function __construct(IPassengersRepository $passengerRepository, TripLogic $tripLogic, UserRepo $uRepo)
    {
        $this->passengerRepository = $passengerRepository;
        $this->tripLogic = $tripLogic;
        $this->uRepo = $uRepo;
    }

    public function getPassengers($tripId, $user, $data)
    {
        if (! $this->tripLogic->tripOwner($user, $tripId) || $this->isUserRequestAccepted($tripId, $user->id)) {
            $this->setErrors(['error' => 'access_denied']);

            return;
        }

        return $this->passengerRepository->getPassengers($tripId, $user, $data);
    }

    public function getPendingRequests($tripId, $user, $data)
    {
        if ($tripId) {
            if (! $this->tripLogic->tripOwner($user, $tripId)) {
                $this->setErrors(['error' => 'access_denied']);

                return;
            }
        }

        return $this->passengerRepository->getPendingRequests($tripId, $user, $data);
    }


    public function getPendingPaymentRequests($tripId, $user, $data)
    {
        if ($tripId) {
            if (! $this->tripLogic->tripOwner($user, $tripId)) {
                $this->setErrors(['error' => 'access_denied']);
                return;
            }
        }

        return $this->passengerRepository->getPendingPaymentRequests($tripId, $user, $data);
    }

    private function validateInput($input)
    {
        return Validator::make($input, [
            'user_id' => 'required|numeric',
            'trip_id' => 'required|numeric',
        ]);
    }

    private function isInputValid($input)
    {
        $validation = $this->validateInput($input);

        if ($result = $validation->fails()) {
            $this->setErrors($validation->errors());
        }

        return true;
    }

    public function newRequest($tripId, $user, $data = [])
    {
        $userId = $user->id;

        $input = [
            'trip_id' => $tripId,
            'user_id' => $userId,
        ];

        if (! $this->isInputValid($input)) {
            return;
        }
        if ($this->passengerRepository->userHasActiveRequest($tripId, $userId)) {
            return;
        }

        $trip = $this->tripLogic->show($user, $tripId);
        if ($trip && ! $trip->expired()) {

            // User Request Limited Module
            $module_user_request_limited = config('carpoolear.module_user_request_limited', false);
            if ($module_user_request_limited && $module_user_request_limited->enabled) {
                $hours_range = $module_user_request_limited->hours_range;
                $userHasRequests = $user->tripsAsPassenger(null, $hours_range, $trip->trip_date)->count() > 0;
                if ($userHasRequests) {
                    $this->setErrors(['error' => 'user_has_another_similar_trip']);
                    return;
                }
            }

            if ($result = $this->passengerRepository->newRequest($tripId, $user, $data)) {
                if ($trip->user->autoaccept_requests) {
                    // $result = $this->passengerRepository->acceptRequest($tripId, $user->id, $trip->user, $data);
                    if (!config('carpoolear.module_trip_seats_payment', false))  {
                        if ($result = $this->passengerRepository->acceptRequest($tripId, $user->id, $trip->user, $data)) {
                            // FIXME uncomented me
                            // event(new AutoRequestEvent($trip, $user, $trip->user));
                            // event(new AcceptEvent($trip, $trip->user, $user));
                        }
                    } else {
                        if ($result = $this->passengerRepository->aproveForPaymentRequest($tripId, $user->id, $trip->user, $data)) {
                            // FIXME uncomented me
                            // event(new AutoRequestEvent($trip, $user, $trip->user));
                            // event(new AcceptEvent($trip, $trip->user, $user));
                        }
                    }
                } else {
                    event(new RequestEvent($trip, $user, $trip->user));
                }
            }
            return $result;
        } else {
            $this->setErrors(['error' => 'access_denied']);
            return;
        }
    }

    public function cancelRequest($tripId, $cancelUserId, $user, $data = [])
    {
        $input = [
            'trip_id' => $tripId,
            'user_id' => $cancelUserId,
        ];

        if (! $this->isInputValid($input)) {
            return;
        }

        $cancelUser = $this->uRepo->show($cancelUserId);
        $trip = $this->tripLogic->show($user, $tripId);

        $canceledState = null;

        if ($this->isUserRequestPending($tripId, $cancelUserId) && $cancelUserId == $user->id) {
            $canceledState = Passenger::CANCELED_REQUEST;
        }
        
        if ($this->isUserRequestAccepted($tripId, $cancelUserId) || $this->isUserRequestWaitingPayment($tripId, $cancelUserId)) {
            if ($cancelUserId == $user->id) {
                if ($this->isUserRequestWaitingPayment($tripId, $cancelUserId)) {
                    $canceledState = Passenger::CANCELED_PASSENGER_WHILE_PAYING;
                } else {
                    $canceledState = Passenger::CANCELED_PASSENGER;
                }
            } else {
                $canceledState = Passenger::CANCELED_DRIVER;
            }
        }
        
        if ($canceledState !== null) {
            if ($result = $this->passengerRepository->cancelRequest($tripId, $cancelUser, $canceledState)) {
                if ($trip->user_id == $user->id) {
                    // event(new CancelEvent($trip, $trip->user, $cancelUser, $canceledState));
                } else {
                    // event(new CancelEvent($trip, $cancelUser, $trip->user, $canceledState));
                }
            }

            return $result;
        } else {
            $this->setErrors(['error' => 'not_a_passenger']);

            return;
        }
    }

    public function sendFullTripMessage ($trip) {
        if (config('carpoolear.module_send_full_trip_message', false) && $trip->user->send_full_trip_message > 0)  {
            if (count($trip->passengerAccepted) >= $trip->seats_available) {
                // tengo mas aceptados que asientos 
                // llamar al manager para que lo haga
                $manager = new \STS\Contracts\Logic\ConversationsManager();
                $manager->sendFullTripMessage($trip);
            }
        }
    }

    public function acceptRequest($tripId, $acceptedUserId, $user, $data = [])
    {
        $input = [
            'trip_id' => $tripId,
            'user_id' => $acceptedUserId,
        ];

        if (! $this->isInputValid($input)) {
            return;
        }

        $acceptedUser = $this->uRepo->show($acceptedUserId);
        $trip = $this->tripLogic->show($user, $tripId);
        if ($this->isUserRequestPending($tripId, $acceptedUserId) && $this->tripLogic->tripOwner($user, $trip)) {
            if ($trip->seats_available == 0) {
                $this->setErrors(['error' => 'not_seat_available']);
                return;
            }

            if (!config('carpoolear.module_trip_seats_payment', false))  {
                if ($result = $this->passengerRepository->acceptRequest($tripId, $acceptedUserId, $user, $data)) {
                    $this->sendFullTripMessage($trip);
                    event(new AcceptEvent($trip, $user, $acceptedUser));
                }
            } else {
                if ($result = $this->passengerRepository->aproveForPaymentRequest($tripId, $acceptedUserId, $user, $data)) {
                    event(new AcceptEvent($trip, $user, $acceptedUser));
                }
            }

            return $result;
        } else {
            $this->setErrors(['error' => 'not_valid_request']);

            return;
        }
    }

    public function transactions($user) {
        $trips = $this->passengerRepository->tripsWithTransactions($user);
        $transactions = [];

        foreach ($trips as $trip) {
            foreach ($trip->passenger as $pas) {
                if (!empty($pas->payment_status)) {
                    $pas->trip = $trip;
                    array_push($transactions, $pas);
                }
            }
        }

        return $transactions; 
    }


    public function payRequest($tripId, $payedUserId, $user, $data = [])
    {
        $input = [
            'trip_id' => $tripId,
            'user_id' => $rejectedUserId,
        ];

        if (! $this->isInputValid($input)) {
            return;
        }

        $payedUser = $this->uRepo->show($payedUserId);
        $trip = $this->tripLogic->show($user, $tripId);
        if (! $this->isUserRequestWaitingPayment($tripId, $payedUserId)) {
            $this->setErrors(['error' => 'not_valid_request']);

            return;
        }

        if ($result = $this->passengerRepository->payRequest($tripId, $payedUserId, $user, $data)) {
            $this->sendFullTripMessage($trip);
            event(new RejectEvent($trip, $user, $payedUser));
        }

        return $result;
    }

    public function rejectRequest($tripId, $rejectedUserId, $user, $data = [])
    {
        $input = [
            'trip_id' => $tripId,
            'user_id' => $rejectedUserId,
        ];

        if (! $this->isInputValid($input)) {
            return;
        }

        $rejectedUser = $this->uRepo->show($rejectedUserId);
        $trip = $this->tripLogic->show($user, $tripId);
        if (! $this->isUserRequestPending($tripId, $rejectedUserId) || ! $this->tripLogic->tripOwner($user, $trip)) {
            $this->setErrors(['error' => 'not_valid_request']);

            return;
        }

        if ($result = $this->passengerRepository->rejectRequest($tripId, $rejectedUserId, $user, $data)) {
            event(new RejectEvent($trip, $user, $rejectedUser));
        }

        return $result;
    }

    public function isUserRequestWaitingPayment($tripId, $userId)
    {
        return $this->passengerRepository->isUserRequestWaitingPayment($tripId, $userId);
    }

    public function isUserRequestAccepted($tripId, $userId)
    {
        return $this->passengerRepository->isUserRequestAccepted($tripId, $userId);
    }

    public function isUserRequestRejected($tripId, $userId)
    {
        return $this->passengerRepository->isUserRequestRejected($tripId, $userId);
    }

    public function isUserRequestPending($tripId, $userId)
    {
        return $this->passengerRepository->isUserRequestPending($tripId, $userId);
    }
}
