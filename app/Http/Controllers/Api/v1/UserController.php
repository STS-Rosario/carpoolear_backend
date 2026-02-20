<?php

namespace STS\Http\Controllers\Api\v1;

use STS\Http\ExceptionWithErrors;
use STS\Http\Resources\UserBadgeResource;
use STS\Models\Donation;
use STS\Models\DeleteAccountRequest;
use STS\Models\User;
use Illuminate\Http\Request;
use STS\Http\Controllers\Controller;
use STS\Services\Logic\UsersManager;
use STS\Transformers\ProfileTransformer;
use STS\Jobs\SendDeleteAccountRequestEmail;

class UserController extends Controller
{
    protected $userLogic;

    public function __construct(UsersManager $userLogic)
    {
        $this->middleware('logged')->except(['create', 'registerDonation', 'bankData', 'terms']);
        $this->middleware('logged.optional')->only(['create', 'registerDonation', 'bankData', 'terms']);
        $this->userLogic = $userLogic;
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
}
