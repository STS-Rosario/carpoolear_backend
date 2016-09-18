<?php

namespace STS\Http\Controllers\Api;

use STS\Services\FacebookService;
use SammyK\LaravelFacebookSdk\LaravelFacebookSdk;
use STS\Http\Controllers\Controller;
use STS\Http\Requests;
use Illuminate\Http\Request; 
use STS\Repository\UsersManager;
use STS\User;
use STS\Entities\Device;
use JWTAuth;

class AuthController extends Controller
{
    protected $user;
    public function __construct(Request $r)
    { 
        $this->middleware('jwt.auth');
        $this->user = \JWTAuth::parseToken()->authenticate();
    }

    public function update(Request $request, UsersManager $manager)
    {
        return $manager->update($this->user, $request->all());
    }

    public function show($id)
    {
        $user = User::find($id);

        $user->cantidadViajes = $user->cantidadViajes();
        $user->distanciaRecorrida = $user->distanciaRecorrida();
        if ($this->user->id != $user->id) {

            $user_id = $this->user->id;
            $patente = $user->trips()->whereHas('passenger',function ($q) use ($user_id) {
                $q->whereUserId($user_id);
                $q->whereRequestState(Passenger::STATE_ACEPTADO);
            })->first();
            if (is_null($patente)) {
                $user->patente = null;

                $user_id = $user->id;
                $dni = $this->user->trips()->whereHas('passenger',function ($q) use ($user_id) {
                    $q->whereUserId($user_id);
                    $q->whereRequestState(Passenger::STATE_ACEPTADO);
                })->first();
                if (is_null($dni)) {
                    $user->nro_doc = null;
                }

            }


        }

        return response()->json($user);
    }

}