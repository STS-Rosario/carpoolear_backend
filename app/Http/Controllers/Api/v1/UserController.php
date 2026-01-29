<?php

namespace STS\Http\Controllers\Api\v1;

use STS\Http\ExceptionWithErrors;
use STS\Http\Resources\UserBadgeResource;
use STS\Models\Donation;
use STS\Models\DeleteAccountRequest;
use STS\Models\Rating;
use STS\Models\User;
use Illuminate\Http\Request;
use STS\Http\Controllers\Controller;
use STS\Services\AnonymizationService;
use STS\Services\Logic\DeviceManager;
use STS\Services\Logic\UsersManager;
use STS\Services\UserDeletionService;
use STS\Transformers\ProfileTransformer;
use STS\Jobs\SendDeleteAccountRequestEmail;
use Tymon\JWTAuth\Facades\JWTAuth;
use STS\Services\MercadoPagoOAuthService;
use Illuminate\Support\Facades\Cache;

class UserController extends Controller
{
    protected $userLogic;
    protected $deviceLogic;
    protected $userDeletionService;
    protected $anonymizationService;

    public function __construct(
        UsersManager $userLogic,
        DeviceManager $deviceLogic,
        UserDeletionService $userDeletionService,
        AnonymizationService $anonymizationService
    ) {
        $this->middleware('logged')->except(['create', 'registerDonation', 'bankData', 'terms']);
        $this->middleware('logged.optional')->only(['create', 'registerDonation', 'bankData', 'terms']);
        $this->userLogic = $userLogic;
        $this->deviceLogic = $deviceLogic;
        $this->userDeletionService = $userDeletionService;
        $this->anonymizationService = $anonymizationService;
    }

    public function create(Request $request)
    {
        $data = $request->all();
        if (config('carpoolear.module_validated_drivers', false))  {
            $files = $request->file('driver_data_docs');
            if (!empty($files)) {
                $docs = array();
                foreach($files as $file) {
                    $tempDoc = $this->userLogic->uploadDoc($file);
                    if (!$tempDoc) {
                        // return response()->json('La imagen' . $file->getClientOriginalName() . $file->getClientOriginalExtension() . ' supera los 4MB.', 422);
                    }
                    $docs[] = $tempDoc;
                }
                $data['driver_data_docs'] = json_encode($docs);
            }
        }
        $user = $this->userLogic->create($data);
        if (! $user) {
            throw new ExceptionWithErrors('Could not create new user.', $this->userLogic->getErrors());
        }

        // return response()->json(['user' => $user]);
        return $this->item($user, new ProfileTransformer(auth()->user()));

    }

    public function update(Request $request)
    {
        $me = auth()->user();
        $data = $request->all();
        if (isset($data['email'])) {
            unset($data['email']);
        }
        $is_driver = config('carpoolear.module_validated_drivers', false) && (isset($data['user_be_driver']) || $me->driver_is_verified);
        // var_dump($is_driver);die;
        $profile = $this->userLogic->update($me, $data, $is_driver);
        if (! $profile) {
            throw new ExceptionWithErrors('Could not update user.', $this->userLogic->getErrors());
        }

        // Check if user's phone number contains any banned numbers
        if (isset($data['mobile_phone'])) {
            $banned_phones = config('carpoolear.banned_phones', []);
            if (!empty($banned_phones)) {
                $user_phone = $data['mobile_phone'];
                foreach ($banned_phones as $banned_phone) {
                    if (str_contains($user_phone, $banned_phone)) {
                        $this->userLogic->update($profile, ['banned' => 1]);
                        \Log::info('User banned due to phone number containing banned number: ' . $user_phone . ' (matched: ' . $banned_phone . ')');
                        break;
                    }
                }
            }
        }
        
        return $this->item($profile, new ProfileTransformer($me));
    }
    
    public function adminUpdate(Request $request) {
        $me = auth()->user();
        if (!$me->is_admin) {
            throw new ExceptionWithErrors('Access denied. Admin privileges required.');
        }
        
        $data = $request->all();
        $user = null;
        
        // Extract user ID from the request
        if (isset($data['user']) && isset($data['user']['id'])) {
            $userId = $data['user']['id'];
            $user = $this->userLogic->show($me, $userId);
            unset($data['user']);
        } else {
            throw new ExceptionWithErrors('User ID is required for admin update.');
        }
        
        if (!$user) {
            throw new ExceptionWithErrors('User not found.');
        }
        
        $profile = $this->userLogic->update($user, $data, false, true);
        if (!$profile) {
            throw new ExceptionWithErrors('Could not update user.', $this->userLogic->getErrors());
        }
        
        return $this->item($profile, new ProfileTransformer($me));
    }

    public function updatePhoto(Request $request)
    {
        $me = auth()->user();
        $profile = $this->userLogic->updatePhoto($me, $request->all());
        if (! $profile) {
            throw new ExceptionWithErrors('Could not update user.', $this->userLogic->getErrors());
        }

        return $this->item($profile, new ProfileTransformer($me));
    }

    public function show($id = null)
    {
        $me = auth()->user();
        if (!($id > 0)) {
            $id = $me->id;
        }
        $profile = $this->userLogic->show($me, $id);
        if (! $profile) {
            throw new ExceptionWithErrors('Users not found.', $this->userLogic->getErrors());
        }

        return $this->item($profile, new ProfileTransformer($me));
    }

    /**
     * Get visible badges for a user.
     */
    public function badges($id = null)
    {
        $me = auth()->user();
        if (!($id > 0) || $id === 'me') {
            $id = $me->id;
        }
        $user = User::find($id);
        if (!$user) {
            throw new ExceptionWithErrors('User not found.');
        }
        $badges = $user->badges()
            ->where('visible', true)
            ->get();

        return UserBadgeResource::collection($badges);
    }

    public function index(Request $request)
    {
        $search_text = null;
        if ($request->has('value')) {
            $search_text = $request->get('value');
        }
        $users = $this->userLogic->index(auth()->user(), $search_text);

        return $this->collection($users, new ProfileTransformer(auth()->user()));
    }
    public function searchUsers (Request $request) {
        $search_text = null;
        if ($request->has('name')) {
            $search_text = $request->get('name');
        }
        $users = $this->userLogic->searchUsers($search_text);
        return $this->collection($users, new ProfileTransformer(auth()->user()));
    }

    public function registerDonation(Request $request)
    {
        $donation = new Donation();
        if ($request->has('has_donated')) {
            $donation->has_donated = $request->get('has_donated');
        }
        if ($request->has('has_denied')) {
            $donation->has_denied = $request->get('has_denied');
        }
        if ($request->has('ammount')) {
            $donation->ammount = $request->get('ammount');
        }
        if ($request->has('trip_id')) {
            $donation->trip_id = $request->get('trip_id');
        }
        $user = null;
        if ($request->has('user')) {
            $user = new \stdClass();
            $user->id = intval($request->get('user'));
            if (! $user->id > 0) {
                $user->id = 164619; //donador anonimo
            }
        } else {
            $user = auth()->user();
        }
        $donation = $this->userLogic->registerDonation($user, $donation);

        return $donation;
    }
    public function bankData(Request $request)
    {
        $data = $this->userLogic->bankData();

        return json_encode($data);
    }


    public function terms (Request $request)
    {
        $lang = $request->has('lang') ? $request->get('lang') : '';
        $data = $this->userLogic->termsText($lang);

        return json_encode($data);
    }

    public function changeBooleanProperty($property, $value, Request $request)
    {
        $user = auth()->user();
        $user->$property = $value > 0;
        $user->save();
        $profile = $this->userLogic->show($user, $user->id);

        return $this->item($profile, new ProfileTransformer($user));

    }

    /**
     * Return Mercado Pago OAuth authorization URL for identity validation.
     * Frontend redirects the user to this URL.
     */
    public function getMercadoPagoOAuthUrl(Request $request, MercadoPagoOAuthService $oauthService)
    {
        if (!config('carpoolear.identity_validation_mercado_pago_enabled', false)) {
            throw new ExceptionWithErrors('Identity validation with Mercado Pago is not available.', [], 503);
        }
        $clientId = config('services.mercadopago.client_id');
        if (empty($clientId) || trim($clientId) === '') {
            throw new ExceptionWithErrors('Mercado Pago OAuth is not configured. Set MERCADO_PAGO_CLIENT_ID and related env vars.', [], 503);
        }

        $user = auth()->user();

        if (empty($user->nro_doc) || trim($user->nro_doc) === '') {
            throw new ExceptionWithErrors('User must have DNI (nro_doc) set to validate identity.', ['nro_doc' => ['required']]);
        }

        $state = bin2hex(random_bytes(16));
        $authResult = $oauthService->getAuthorizationUrl($state);

        if (is_array($authResult)) {
            $authorizationUrl = $authResult['authorization_url'];
            Cache::put('mp_oauth_state:' . $state, [
                'user_id' => $user->id,
                'code_verifier' => $authResult['code_verifier'],
            ], 600);
        } else {
            $authorizationUrl = $authResult;
            Cache::put('mp_oauth_state:' . $state, ['user_id' => $user->id], 600);
        }

        return response()->json(['authorization_url' => $authorizationUrl]);
    }

    public function deleteAccountRequest(Request $request)
    {
        $user = auth()->user();

        // Create a new delete account request
        $deleteRequest = new DeleteAccountRequest();
        $deleteRequest->user_id = $user->id;
        $deleteRequest->date_requested = now();
        $deleteRequest->action_taken = DeleteAccountRequest::ACTION_REQUESTED;
        $deleteRequest->save();

        // Send email notification to admin using the same mechanism as password reset
        $adminEmail = 'carpoolear@stsrosario.org.ar';
        $adminUrl = 'https://carpoolear.com.ar/app/admin';

        // Queue the email sending job instead of sending synchronously
        SendDeleteAccountRequestEmail::dispatch($adminEmail, $adminUrl)
            ->onQueue('emails') // Use a dedicated queue for emails
            ->delay(now()->addSeconds(10)); // Add a small delay to prevent immediate retries

        \Log::info('Delete account request email queued successfully', [
            'user_id' => $user->id,
            'admin_email' => $adminEmail
        ]);

        return response()->json([
            'message' => 'Delete account request created successfully',
            'request_id' => $deleteRequest->id
        ], 201);
    }

    /**
     * Automated account deletion or anonymization based on user data.
     * Does not create DeleteAccountRequest. Logs the action.
     */
    public function deleteAccount(Request $request)
    {
        $user = auth()->user();
        $token = JWTAuth::getToken();

        $hasTrips = $user->trips()->exists() || $user->tripsAsPassenger()->exists();
        $hasRatings = $user->ratingReceived()->exists() || $user->ratingGiven()->exists();
        $hasReferences = $user->referencesReceived()->exists();
        $hasNegativeRatings = $user->ratings(Rating::STATE_NEGATIVO)->exists();

        if ($hasNegativeRatings) {
            return response()->json([
                'message' => 'Debido a que tenés calificaciones negativas necesitamos que te pongas en contacto con la mesa de ayuda para proceder con el borrado de tu cuenta',
                'error' => 'negative_ratings',
            ], 422);
        }

        if (!$hasTrips && !$hasRatings && !$hasReferences) {
            // Branch A: Delete user
            $userId = $user->id;
            $this->deviceLogic->logoutAllDevices($user);
            $this->userDeletionService->deleteUser($user);

            try {
                if ($token) {
                    JWTAuth::invalidate($token);
                }
            } catch (\Exception $e) {
                \Log::error('Failed to invalidate JWT after delete: ' . $e->getMessage());
            }

            \Log::info('User self-deletion: actual_delete', ['user_id' => $userId]);

            return response()->json([
                'message' => 'Cuenta eliminada exitosamente',
                'action' => 'deleted',
            ]);
        }

        // Branch B: Anonymize user
        $this->deviceLogic->logoutAllDevices($user);
        $this->anonymizationService->anonymize($user);

        try {
            if ($token) {
                JWTAuth::invalidate($token);
            }
        } catch (\Exception $e) {
            \Log::error('Failed to invalidate JWT after anonymize: ' . $e->getMessage());
        }

        \Log::info('User self-deletion: anonymize', ['user_id' => $user->id]);

        return response()->json([
            'message' => 'Cuenta anonimizada exitosamente',
            'action' => 'anonymized',
        ]);
    }
}
