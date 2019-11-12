<?php

namespace STS\Services\Logic;

use STS\User;
use Validator;
use Illuminate\Validation\Rule;
use STS\Entities\Trip;
use STS\Repository\FileRepository;
use STS\Events\User\Reset  as ResetEvent;
use STS\Contracts\Logic\User as UserLogic;
use STS\Events\User\Create as CreateEvent;
use STS\Events\User\Update as UpdateEvent;
use STS\Contracts\Repository\User as UserRep;

class UsersManager extends BaseManager implements UserLogic
{
    protected $repo;

    public function __construct(UserRep $userRep)
    {
        $this->repo = $userRep;
    }

    public function validator(array $data, $id = null, $is_social = false, $is_driver = false)
    {
        if ($id) {
            $rules = [
                'name'     => 'max:255',
                'email'    => 'email|max:255|unique:users,email,'.$id,
                'password' => 'min:6|confirmed',
                // 'gender'   => 'string|in:Masculino,Femenino,N/A',
            ];
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
        $v = $this->validator($data, null, $is_social, $is_driver);
        if ($v->fails() && $validate) {
            $this->setErrors($v->errors());
            return;
        } else {
            $data['emails_notifications'] = true;
            if (isset($data['password'])) {
                $data['password'] = bcrypt($data['password']);
            }
            if (! isset($data['active'])) {
                $data['active'] = false;
                $data['activation_token'] = str_random(40);
            }
            $u = $this->repo->create($data);
            event(new CreateEvent($u->id));

            return $u;
        }
    }

    public function update($user, array $data, $is_driver = false)
    {
        $v = $this->validator($data, $user->id, null, $is_driver);
        if ($v->fails()) {
            $this->setErrors($v->errors());
            return;
        } else {
            if (isset($data['password'])) {
                $data['password'] = bcrypt($data['password']);
            }
            $img_names = [];
            if ($is_driver && count($data['driver_data_docs'])) {
                foreach ($data['driver_data_docs'] as $file) {
                    if ($file) {
                        $img_names[] = $this->uploadDoc($file);
                    }
                }
                $data['driver_data_docs'] = json_encode($img_names);
            } else {
                if (count($data['driver_data_docs'])) {
                    $data['driver_data_docs'] = json_encode($data['driver_data_docs']);
                }
            }
            $this->repo->update($user, $data);

            if ($is_driver && count($data['driver_data_docs']) && !$user->driver_is_verified) {
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
        $user = $this->repo->getUserBy('email', $email);
        if ($user) {
            $token = str_random(40);
            $this->repo->deleteResetToken('email', $user->email);
            $this->repo->storeResetToken($user, $token);
            $this->repo->update($user, ['active' => false]);
            event(new ResetEvent($user->id, $token));

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
        $country = config('carpoolear.osm_country');
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
            $path = $path . $app_name . '_' . $request->get('lang') . '.html';
        } else {
            $path = $path . $app_name . '.html';
        }
        $html = file_get_contents($path);
        $response = new \stdClass();
        $response->content = $html;
        return $response;
    }
}
