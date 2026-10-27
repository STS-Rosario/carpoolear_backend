<?php

namespace STS\Http\Controllers\Api\v1;

use Illuminate\Http\Request;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\MercadoPagoConfig;
use STS\Helpers\IdentityValidationHelper;
use STS\Helpers\OldCordovaAppHelper;
use STS\Helpers\SelladoQrPayment;
use STS\Http\Controllers\Controller;
use STS\Http\ExceptionWithErrors;
use STS\Models\Trip;
use STS\Repository\TripSearchRepository;
use STS\Services\Logic\TripsManager;
use STS\Services\MercadoPagoService;
use STS\Services\SelladoPaymentApplier;
use STS\Services\TripInstagramStoryGenerator;
use STS\Transformers\TripTransformer;

class TripController extends Controller
{
    protected $user;

    protected $tripsLogic;

    protected $tripSearchRepository;

    public function __construct(Request $r, TripsManager $tripsLogic, TripSearchRepository $tripSearchRepository)
    {
        $this->middleware('logged')->except(['search']);
        $this->middleware('logged.optional')->only('search');
        $this->tripsLogic = $tripsLogic;
        $this->tripSearchRepository = $tripSearchRepository;
    }

    public function create(Request $request)
    {
        $this->user = auth()->user();
        if (! IdentityValidationHelper::canPerformRestrictedActions($this->user)) {
            throw new ExceptionWithErrors(IdentityValidationHelper::identityValidationRequiredMessage(), IdentityValidationHelper::identityValidationRequiredError());
        }
        $data = $request->all();
        $trip = $this->tripsLogic->create($this->user, $data);
        if (! $trip) {
            throw new ExceptionWithErrors(
                $this->messageForTripWriteErrors($this->tripsLogic->getErrors(), 'Could not create new trip.'),
                $this->tripsLogic->getErrors()
            );
        }

        return $this->item($trip, new TripTransformer($this->user));
        // return response()->json(['data' => $trip]);
    }

    public function update($id, Request $request)
    {
        $this->user = auth()->user();
        $data = $request->all();
        $trip = $this->tripsLogic->update($this->user, $id, $data);
        if (! $trip) {
            throw new ExceptionWithErrors(
                $this->messageForTripWriteErrors($this->tripsLogic->getErrors(), 'Could not update trip.'),
                $this->tripsLogic->getErrors()
            );
        }

        return $this->item($trip, new TripTransformer($this->user));
        // return response()->json(['data' => $trip]);
    }

    public function changeTripSeats($id, Request $request)
    {
        $this->user = auth()->user();
        $increment = $request->get('increment');
        $trip = $this->tripsLogic->changeTripSeats($this->user, $id, $increment);
        if (! $trip) {
            throw new ExceptionWithErrors('Could not update trip.', $this->tripsLogic->getErrors());
        }

        return $this->item($trip, new TripTransformer($this->user));
    }

    public function delete($id, Request $request)
    {
        $this->user = auth()->user();
        $result = $this->tripsLogic->delete($this->user, $id);
        if (! $result) {
            throw new ExceptionWithErrors('Could not delete trip.', $this->tripsLogic->getErrors());
        }

        return response()->json(['data' => 'ok']);
    }

    public function inviteFriends($id, Request $request)
    {
        $this->user = auth()->user();
        $friendIds = $request->get('friend_ids', []);
        $count = $this->tripsLogic->inviteFriends($this->user, $id, $friendIds);
        if ($count === null) {
            throw new ExceptionWithErrors('Could not invite friends.', $this->tripsLogic->getErrors());
        }

        return response()->json(['invited_count' => $count]);
    }

    public function show($id, Request $request)
    {
        if (OldCordovaAppHelper::isOldCordovaApp()) {
            return response()->json([
                'data' => OldCordovaAppHelper::getFakeTripData(),
            ]);
        }

        $this->user = auth()->user();
        $trip = $this->tripsLogic->show($this->user, $id);
        if (! $trip) {
            throw new ExceptionWithErrors('Could not found trip.', $this->tripsLogic->getErrors());
        }

        return $this->item($trip, new TripTransformer($this->user));
        // return response()->json(['data' => $trip]);
    }

    public function instagramStory($id, TripInstagramStoryGenerator $storyGenerator)
    {
        $this->user = auth()->user();
        $trip = $this->tripsLogic->show($this->user, $id);
        if (! $trip) {
            throw new ExceptionWithErrors('Could not found trip.', $this->tripsLogic->getErrors());
        }

        return response($storyGenerator->generate($trip), 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'inline; filename="trip-'.$trip->id.'-instagram-story.png"',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    public function search(Request $request)
    {
        if (OldCordovaAppHelper::isOldCordovaApp()) {
            return response()->json([
                'data' => [OldCordovaAppHelper::getFakeTripData()],
                'meta' => [
                    'pagination' => [
                        'total' => 1,
                        'count' => 1,
                        'per_page' => 20,
                        'current_page' => 1,
                        'total_pages' => 1,
                        'links' => [
                            'previous' => null,
                            'next' => null,
                        ],
                    ],
                ],
            ]);
        }

        $data = $request->all();

        if (! isset($data['page_size'])) {
            $data['page_size'] = 20;
        }

        $this->user = auth('api')->user();
        $trips = $this->tripsLogic->search($this->user, $data);

        // Track the search for advertising purposes
        $this->trackSearch($data, $trips);

        // / return $trips;
        return $this->paginator($trips, new TripTransformer($this->user));
    }

    private function trackSearch($data, $trips)
    {
        try {
            $originId = isset($data['origin_id']) ? $data['origin_id'] : null;
            $destinationId = isset($data['destination_id']) ? $data['destination_id'] : null;
            $searchDate = isset($data['date']) ? $data['date'] : null;
            $isPassenger = isset($data['is_passenger']) ? parse_boolean($data['is_passenger']) : false;

            // Track searches with either origin, destination, or both
            if ($originId || $destinationId) {
                $clientPlatform = 0; // Default to web for now
                $this->tripSearchRepository->trackSearch($this->user, $originId, $destinationId, $trips, $clientPlatform, $searchDate, $isPassenger);
            }
        } catch (\Exception $e) {
            // Log error but don't break the search functionality
            \Log::error('Error tracking trip search: '.$e->getMessage());
        }
    }

    public function getTrips(Request $request)
    {
        $this->user = auth()->user();

        if ($request->has('as_driver')) {
            $asDriver = parse_boolean($request->get('as_driver'));
        } else {
            $asDriver = true;
        }
        $targetUserId = $this->resolveTargetUserId($request);
        $trips = $this->tripsLogic->getTrips($this->user, $targetUserId, $asDriver);

        return $this->collection($trips, new TripTransformer($this->user));
        // return response()->json(['data' => $trips]);
    }

    public function getOngoingTrip(Request $request)
    {
        $this->user = auth()->user();
        $trip = $this->tripsLogic->getOngoingTrip($this->user);

        if (! $trip) {
            return response()->json(['data' => null]);
        }

        return $this->item($trip, new TripTransformer($this->user));
    }

    public function getOldTrips(Request $request)
    {
        $this->user = auth()->user();

        if ($request->has('as_driver')) {
            $asDriver = parse_boolean($request->get('as_driver'));
        } else {
            $asDriver = true;
        }

        $targetUserId = $this->resolveTargetUserId($request);
        $trips = $this->tripsLogic->getOldTrips($this->user, $targetUserId, $asDriver);

        return $this->collection($trips, new TripTransformer($this->user));
    }

    private function resolveTargetUserId(Request $request): int
    {
        if ($request->has('user_id') && $this->user->is_admin) {
            return (int) $request->get('user_id');
        }

        return $this->user->id;
    }

    public function price(Request $request)
    {
        $data = $request->all();

        $from = isset($data['from']) ? $data['from'] : null;
        $to = isset($data['to']) ? $data['to'] : null;
        $distance = isset($data['distance']) ? $data['distance'] : null;

        return $this->tripsLogic->price($from, $to, $distance);
    }

    public function getTripInfo(Request $request)
    {
        $data = $request->all();
        $points = isset($data['points']) ? $data['points'] : null;

        return $this->tripsLogic->getTripInfo($points);
    }

    public function selladoViaje(Request $request)
    {
        $this->user = auth()->user();

        $infoSelladoViaje = $this->tripsLogic->selladoViaje($this->user);

        return response()->json([
            'success' => true,
            'data' => $infoSelladoViaje,
        ]);
    }

    public function createSelladoQrOrder($id, MercadoPagoService $mpService)
    {
        $this->user = auth()->user();
        if (! SelladoQrPayment::isEnabled()) {
            throw new ExceptionWithErrors('QR payment is not available.', [], 503);
        }

        $trip = $this->tripsLogic->show($this->user, $id);
        if (! $trip) {
            throw new ExceptionWithErrors('Could not found trip.', $this->tripsLogic->getErrors());
        }
        if ((int) $trip->user_id !== (int) $this->user->id) {
            throw new ExceptionWithErrors('Could not found trip.', []);
        }
        if (! $trip->needs_sellado || $trip->state === Trip::STATE_READY) {
            throw new ExceptionWithErrors('This trip does not need Un litro payment.', []);
        }

        $result = $mpService->createQrOrderForSellado(
            $trip,
            (int) config('carpoolear.module_trip_creation_payment_amount_cents')
        );

        return response()->json($result);
    }

    public function syncSelladoPayment($id, Request $request, SelladoPaymentApplier $applier)
    {
        $this->user = auth()->user();
        $trip = $this->tripsLogic->show($this->user, $id);
        if (! $trip) {
            throw new ExceptionWithErrors('Could not found trip.', $this->tripsLogic->getErrors());
        }
        if ((int) $trip->user_id !== (int) $this->user->id) {
            throw new ExceptionWithErrors('Could not found trip.', []);
        }

        $paymentId = $request->input('payment_id');
        if (! $paymentId) {
            throw new ExceptionWithErrors('payment_id is required.', []);
        }

        $mpPayment = $this->fetchMercadoPagoPayment($paymentId);
        if (! $mpPayment) {
            throw new ExceptionWithErrors('Could not fetch payment.', []);
        }

        $paymentTripId = $applier->tripIdFromExternalReference($mpPayment['external_reference'] ?? '');
        if ($paymentTripId !== (int) $trip->id) {
            throw new ExceptionWithErrors('This payment does not belong to the trip.', []);
        }

        $applier->record($trip, $mpPayment);

        return $this->item($trip->fresh(), new TripTransformer($this->user));
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function fetchMercadoPagoPayment($paymentId): ?array
    {
        MercadoPagoConfig::setAccessToken(config('services.mercadopago.access_token'));
        try {
            $payment = (new PaymentClient)->get($paymentId);
            $content = $payment?->getResponse()?->getContent();
            $decoded = is_array($content) ? $content : json_decode((string) $content, true);
            if (is_array($decoded) && isset($decoded['id'])) {
                return [
                    'id' => $decoded['id'],
                    'status' => $decoded['status'] ?? 'pending',
                    'status_detail' => $decoded['status_detail'] ?? null,
                    'transaction_amount' => $decoded['transaction_amount'] ?? ($decoded['amount'] ?? 0),
                    'amount' => $decoded['transaction_amount'] ?? ($decoded['amount'] ?? 0),
                    'currency_id' => $decoded['currency_id'] ?? null,
                    'payment_method_id' => $decoded['payment_method_id'] ?? null,
                    'payment_type_id' => $decoded['payment_type_id'] ?? null,
                    'external_reference' => $decoded['external_reference'] ?? '',
                    'date_created' => $decoded['date_created'] ?? null,
                    'date_approved' => $decoded['date_approved'] ?? null,
                    'date_last_updated' => $decoded['date_last_updated'] ?? null,
                ];
            }
        } catch (\Throwable $e) {
            return null;
        }

        return null;
    }

    public function changeVisibility($id, Request $request)
    {
        $this->user = auth()->user();
        $trip = $this->tripsLogic->changeVisibility($this->user, $id);
        if (! $trip) {
            throw new ExceptionWithErrors('Could not update trip.', $this->tripsLogic->getErrors());
        }

        return $this->item($trip, new TripTransformer($this->user));
    }

    /**
     * @param  mixed  $errors
     */
    private function messageForTripWriteErrors($errors, string $defaultMessage): string
    {
        if ($this->errorsContainCode($errors, 'routing_service_unavailable')) {
            return trans('errors.routing_service_unavailable');
        }

        return $defaultMessage;
    }

    /**
     * @param  mixed  $errors
     */
    private function errorsContainCode($errors, string $code): bool
    {
        if ($errors === null) {
            return false;
        }
        $arr = is_object($errors) && method_exists($errors, 'toArray') ? $errors->toArray() : (array) $errors;
        $list = $arr['error'] ?? null;
        if ($list === null) {
            return false;
        }
        $values = is_array($list) ? $list : [$list];

        return in_array($code, $values, true);
    }
}
