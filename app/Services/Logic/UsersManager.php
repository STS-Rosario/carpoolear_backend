<?php

namespace STS\Services\Logic;

use STS\Models\Passenger;
use STS\Models\User;
use STS\Repository\TripRepository;
use STS\Repository\UserRepository;
use Validator;
use STS\Models\Trip;
use STS\Repository\FileRepository;
use STS\Events\User\Create as CreateEvent;
use STS\Events\User\Update as UpdateEvent; 
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use STS\Mail\ResetPassword;

class UsersManager extends BaseManager
{
    protected $repo;
    protected $tripRepository;

    public function __construct(UserRepository $userRep, TripRepository $tripRepository)
    {
        $this->repo = $userRep;
        $this->tripRepository = $tripRepository;
    }

    public function validator(array $data, $id = null, $is_social = false, $is_driver = false, $is_admin = false)
    {
        if ($id) {
            $rules = [
                'name'     => 'max:255',
                'email'    => 'email|max:255|unique:users,email,'.$id,
                'password' => 'min:6|confirmed',
            ];
            if (config('carpoolear.module_unique_doc_phone', false) && !$is_admin)  {
                $rules['nro_doc'] = 'unique:users,nro_doc,'.$id;
                $rules['mobile_phone'] = 'unique:users,mobile_phone,'.$id;
            }
        } else {
            if (!$is_social) {
                $rules = [
                    'name'     => 'required|max:255',
                    'email'    => 'required|email|max:255|unique:users',
                    'password' => 'min:6|confirmed',
                    // 'gender'   => 'string|in:Masculino,Feminino,N/A',
                    'emails_notifications' => 'boolean',
                ];
            } else {
                $rules = [
                    'name'     => 'required|max:255',
                    'email'    => 'present|email|max:255|unique:users',
                    'password' => 'min:6|confirmed',
                    // 'gender'   => 'string|in:Masculino,Feminino,N/A',
                    'emails_notifications' => 'boolean',
                ];
            }
        }
        if (config('carpoolear.module_validated_drivers', false) && $is_driver)  {
            $rules['driver_data_docs'] = 'required|array|min:1';
        }
        if ($is_admin) {
            unset($rules['email']);
        }
        $validator = Validator::make($data, $rules);
        return $validator;
    }

    /**
     * Create a new user instance after a valid registration.
     *
     * @param array $data
     *
     * @return User
     */
    public function create(array $data, $validate = true, $is_social = false, $is_driver = false)
    {
        \Log::info('Create USER: ' . $data['name']);
        $v = $this->validator($data, null, $is_social, $is_driver);
        if ($v->fails() && $validate) {
            $this->setErrors($v->errors());

            \Log::info('Error validation: ' . $data['name']);
            return;
        } else {
            $data['emails_notifications'] = true;
            if (isset($data['password'])) {
                $data['password'] = bcrypt($data['password']);
            }
            // if token (reCAPTCHA) is not present, use email confirmation
            if (!isset($data['token'])) {
                if (! isset($data['active'])) {
                    $data['active'] = false;
                    $data['activation_token'] = Str::random(40);

                    $u = $this->repo->create($data);

                    // Check if user name contains any banned words
                    $banned_words = config('carpoolear.banned_words_names', []);
                    if (!empty($banned_words)) {
                        $user_name_lower = strtolower($u->name);
                        foreach ($banned_words as $word) {
                            if (str_contains($user_name_lower, strtolower($word))) {
                                $this->repo->update($u, ['banned' => 1]);
                                \Log::info('User banned due to name containing banned word: ' . $u->name . ' (matched: ' . $word . ')');
                                break;
                            }
                        }
                    }

                    \Log::info('UserManager before CreateEvent');
                    event(new CreateEvent($u->id));

                    return $u;
                }
            } else {
                // if we have reCAPTCHA token, skip email verification
                $data['active'] = true;

                $url = "https://www.google.com/recaptcha/api/siteverify";

                \Log::info('Captcha val: ' . env('RECAPTCHA_SECRET_KEY', '123456789') . ' - ip  ' . $_SERVER['REMOTE_ADDR'] . ' token = '. $_POST['token']);
                $recaptchaData = [
                    'secret' => env('RECAPTCHA_SECRET_KEY', ''),
                    'response' => $_POST['token'],
                    'remoteip' => $_SERVER['REMOTE_ADDR']
                ];

                $options = array(
                    'http' => array(
                    'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                    'method'  => 'POST',
                    'content' => http_build_query($recaptchaData)
                    )
                    );
                
                # Creates and returns stream context with options supplied in options preset 
                $context  = stream_context_create($options);
                # file_get_contents() is the preferred way to read the contents of a file into a string
                $response = file_get_contents($url, false, $context);
                # Takes a JSON encoded string and converts it into a PHP variable
                $res = json_decode($response, true);

                \Log::info('Captcha val: ' . $response);
                # END setting reCaptcha v3 validation data
                
                # Post form OR output alert and bypass post if false. NOTE: score conditional is optional
                # since the successful score default is set at >= 0.5 by Google. Some developers want to
                # be able to control score result conditions, so I included that in this example.

                if ($res['success'] == true && $res['score'] >= 0.5) {
                // if (true) {
                    $u = $this->repo->create($data);

                    // Check if user name contains any banned words
                    $banned_words = config('carpoolear.banned_words_names', []);
                    if (!empty($banned_words)) {
                        $user_name_lower = strtolower($u->name);
                        foreach ($banned_words as $word) {
                            if (str_contains($user_name_lower, strtolower($word))) {
                                $this->repo->update($u, ['banned' => 1]);
                                \Log::info('User banned due to name containing banned word: ' . $u->name . ' (matched: ' . $word . ')');
                                break;
                            }
                        }
                    }

                    \Log::info('UserManager before CreateEvent.');
                    event(new CreateEvent($u->id));

                    return $u;
                } else {

                    \Log::info('captcha failed: ' . $data['name']);
                    return false;
                }
            }
            
            
        }
    }

    public function update($user, array $data, $is_driver = false, $is_admin = false)
    {
        \Log::info('update manager: ' . $user->name);
        $v = $this->validator($data, $user->id, null, $is_driver, $is_admin);
        if ($v->fails()) {
            \Log::info('update manager: ' . $user->name . ' failedddd why?');
            \Log::info($v->errors());
            $this->setErrors($v->errors());
            return;
        } else {
            if (isset($data['password'])) {
                $data['password'] = bcrypt($data['password']);
            }
            $img_names = [];
            if (isset($data['driver_data_docs'])) {
                if ($is_driver && is_array($data['driver_data_docs']) && count($data['driver_data_docs'])) {
                    foreach ($data['driver_data_docs'] as $file) {
                        if ($file) {
                            if (is_string($file)) {
                                $img_names[] = $file;
                            } else {
                                $img_names[] = $this->uploadDoc($file);
                            }
                        }
                    }
                    $data['driver_data_docs'] = json_encode($img_names);
                } else {
                    if (is_array($data['driver_data_docs']) && count($data['driver_data_docs'])) {
                        $data['driver_data_docs'] = json_encode($data['driver_data_docs']);
                    }
                }
            }
            \Log::info($data);
            $this->repo->update($user, $data);
            if ($user->banned > 0) {
                // hide user trips
                $this->tripRepository->hideTrips($user);
            } else {
                $this->tripRepository->unhideTrips($user);
            }

            if (isset($data['driver_data_docs'])) {
                if ($is_driver && is_array($data['driver_data_docs']) && count($data['driver_data_docs']) && !$user->driver_is_verified) {
                    // send email to admin
                    $email_admin = config('carpoolear.admin_email', '');
                    if (!empty($email_admin)) {
                        $data = [
                            'title' => 'Usuario quiere ser conductor',
                            'user' => $user
                        ];
                        \Mail::send('email.user_be_driver', $data, function ($message) use ($email_admin, $data) {
                            $message->to($email_admin, 'Admin')->subject($data['title']);
                        });
                    }
                }
            }
            event(new UpdateEvent($user->id));

            return $user;
        }
    }

    public function mailUnsuscribe($email)
    {
        $data['emails_notifications'] = false;
        $user = $this->repo->getUserBy('email', $email);
        $this->repo->update($user, $data);

        return $user;
    }

    public function uploadDoc ($file) {
        $mil = str_replace(".", "", microtime());
        $mil = str_replace(" ", "", $mil);
        $newfilename = date('mdYHis') . $mil;
        $imageName = $newfilename . "." . $file->getClientOriginalExtension();

        if ($file->getClientSize() > 4096 * 1024 ) {
            return false;
        }
        $file->move(
            base_path() . '/public/image/docs/', $imageName
        );
        return $imageName;
    }

    public function updatePhoto($user, $data)
    {
        $v = Validator::make($data, ['profile' => 'required']);
        if ($v->fails()) {
            $this->setErrors($v->errors());

            return;
        } else {
            $fileManager = new FileRepository();
            $base64_string = $data['profile'];

            $data = explode(',', $base64_string);
            if (is_array($data) && count($data) > 1) {
                $data = base64_decode($data[1]);

                $name = $fileManager->createFromData($data, 'jpeg', 'image/profile');

                $this->repo->updatePhoto($user, $name);

                event(new UpdateEvent($user->id));

                return $user;
            } else {
                $error = new \stdClass();
                $error->error = 'error_uploading_image';
                $this->setErrors($error);

                return;
            }
        }
    }

    public function find($user_id)
    {
        return $this->repo->show($user_id);
    }

    public function activeAccount($activation_token)
    {
        $user = $this->repo->getUserBy('activation_token', $activation_token);
        if ($user) {
            $this->repo->update($user, ['active' => true, 'activation_token' => null]);

            return $user;
        } else {
            $this->setErrors(['error' => 'invalid_activation_token']);

            return;
        }
    }

    public function resetPassword($email)
    {
        \Log::info('resetPassword userManager');
        $user = $this->repo->getUserBy('email', $email);
        if ($user) {
            $token = Str::random(40);
            $this->repo->deleteResetToken('email', $user->email);
            $this->repo->storeResetToken($user, $token);

            \Log::info('resetPassword before event'); 
            
            $domain = config('app.url');
            $name_app = config('carpoolear.name_app');
            $url = config('app.url').'/app/reset-password/'. $token;
            $html = view('email.reset_password', compact('token', 'user', 'url', 'name_app', 'domain'))->render();
             
            Mail::to($user->email)->send(new ResetPassword($token, $user, $url, $name_app, $domain));

            \Log::info('resetPassword post event event');
            return $token;
        } else {
            $this->setErrors(['error' => 'user_not_found']);

            return;
        }
    }

    public function changePassword($token, $data)
    {
        $user = $this->repo->getUserByResetToken($token);
        if ($user) {
            $data['active'] = true;
            if ($this->update($user, $data)) {
                $this->repo->deleteResetToken('email', $user->email);

                return true;
            }
        }
    }

    public function show($user, $profile_id)
    {
        $profile = $this->repo->show($profile_id);
        if ($profile) {
            // $profile->donations = $profile->donations->get();
            return $profile;
        }
        $this->setErrors(['error' => 'profile not found']);
    }

    public function index($user, $search_text)
    {
        return $this->repo->index($user, $search_text);
    }

    public function tripsCount($user, $type = null)
    {
        $cantidad = 0;
        if ($type == Passenger::TYPE_CONDUCTOR || is_null($type)) {
            $cantidad += $user->trips(Trip::FINALIZADO)->count();
        }
        if ($type == Passenger::TYPE_PASAJERO || is_null($type)) {
            $cantidad += $user->tripsAsPassenger(Trip::FINALIZADO)->count();
        }

        return $cantidad;
    }

    public function tripsDistance($user, $type = null)
    {
        $distancia = 0;
        if ($type == Passenger::TYPE_CONDUCTOR || is_null($type)) {
            $distancia += $user->trips(Trip::FINALIZADO)->sum('distance');
        }
        if ($type == Passenger::TYPE_PASAJERO || is_null($type)) {
            $distancia += $user->tripsAsPassenger(Trip::FINALIZADO)->sum('distance');
        }

        return $distancia;
    }

    public function unansweredConversationOrRequestsByTrip ($trip) {
        $count = $this->repo->unansweredConversationOrRequestsByTrip($trip->user_id, $trip->id);
        \Log::info('unansweredConversationOrRequestsByTrip: ' . $count . ' < ' . $trip->user->unaswered_messages_limit);
        if (isset($trip->user->unaswered_messages_limit) && $trip->user->unaswered_messages_limit > 0) {
            return $count < $trip->user->unaswered_messages_limit;
        } else {
            return true;
        }
    }

    public function searchUsers ($name) {
        return $this->repo->searchUsers($name);
    }

    public function registerDonation($user, $donation)
    {
        $donation->user_id = $user->id;
        $donation->month = date('Y-m-01 00:00:00');
        $donation->save();

        return $donation;
    }

    public function bankData()
    {
        $bankPath = storage_path() . '/banks/';
        $ccPath = storage_path() . '/cc/';
        $country = config('carpoolear.osm_country', 'ARG');
        $banks = json_decode(file_get_contents($bankPath . $country . '.json'), true);
        $cc = json_decode(file_get_contents($ccPath . $country . '.json'), true);

        return (object)[
            'cc' => $cc,
            'banks' => $banks
        ];
    }
    public function termsText($lang)
    {
        $path = storage_path() . '/terms/';
        $app_name = config('carpoolear.target_app');
        if (!empty($lang)) {
            $path = $path . $app_name . '_' . $lang . '.html';
        } else {
            $path = $path . $app_name . '.html';
        }
        $html = file_get_contents($path);
        $response = new \stdClass();
        $response->content = $html;
        return $response;
    }
}
