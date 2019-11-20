<?php

namespace STS\Services\Logic;

use Validator;
use STS\Entities\Trip;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\MessageBag;
use STS\Contracts\Logic\Trip as TripLogic;
use STS\Events\Trip\Create  as CreateEvent;
use STS\Events\Trip\Delete  as DeleteEvent;
use STS\Events\Trip\Update  as UpdateEvent;
use STS\Contracts\Repository\Trip as TripRepo;

class TripsManager extends BaseManager implements TripLogic
{
    protected $tripRepo;

    public function __construct(TripRepo $trips)
    {
        $this->tripRepo = $trips;
    }

    public function validator(array $data, $user_id, $id = null)
    {
        if (is_null($id)) {
            return Validator::make($data, [
                'is_passenger'          => 'required|in:0,1',
                'from_town'             => 'required|string|max:255',
                'to_town'               => 'required|string|max:255',
                'trip_date'             => 'required|date|after:now',
                'total_seats'           => 'required|integer|max:5|min:1',
                'friendship_type_id'    => 'required|integer|in:0,1,2',
                'estimated_time'        => 'required|string',
                'distance'              => 'required|numeric',
                'co2'                   => 'required|numeric',
                'description'           => 'string',
                'return_trip_id'        => 'exists:trips,id',
                'parent_trip_id'        => 'exists:trips,id',
                'car_id'                => 'exists:cars,id,user_id,'.$user_id,

                'points.*.address'      => 'required|string',
                'points.*.json_address' => 'required|array',
                'points.*.lat'          => 'required|numeric',
                'points.*.lng'          => 'required|numeric',
            ]);
        } else {
            return Validator::make($data, [
                'is_passenger'          => 'in:0,1',
                'from_town'             => 'string|max:255',
                'to_town'               => 'string|max:255',
                'trip_date'             => 'date|after:now',
                'total_seats'           => 'integer|max:5|min:1',
                'friendship_type_id'    => 'integer|in:0,1,2',
                'estimated_time'        => 'string',
                'distance'              => 'numeric',
                'co2'                   => 'numeric',
                'return_trip_id'        => 'exists:trips,id',
                'parent_trip_id'        => 'exists:trips,id',
                'car_id'                => 'exists:cars,id,user_id,'.$user_id,

                'points.*.address'      => 'string',
                'points.*.json_address' => 'array',
                'points.*.lat'          => 'numeric',
                'points.*.lng'          => 'numeric',
            ]);
        }
    }

    public function create($user, array $data)
    {
        $v = $this->validator($data, $user->id);
        if ($v->fails()) {
            $this->setErrors($v->errors());
            return;
        } else {
            if (config('carpoolear.module_validated_drivers', false) && !$user->driver_is_verified && $data['is_passenger'] == 0) {
                $messageBag = new MessageBag;
                $messageBag->add('driver_is_verified', 'The driver must be verified.');
                $this->setErrors($messageBag);
                return;
            }
            $data['user_id'] = $user->id;
            $trip = $this->tripRepo->create($data);
            if (isset($data['parent_trip_id'])) {
                $parentTripId = $data['parent_trip_id'];
                $parentTrip = $this->tripRepo->show($user, $parentTripId);

                if ($parentTrip) {
                    $parentData = [];
                    $parentData['return_trip_id'] = $trip->id;

                    $parentTrip = $this->tripRepo->update($parentTrip, $parentData);
                }
            }
            // REVIEW uncomented me
            // event(new CreateEvent($trip));

            return $trip;
        }
    }

    public function update($user, $trip_id, array $data)
    {
        $trip = $this->tripRepo->show($user, $trip_id);
        if ($trip) {
            if ($user->id == $trip->user->id || $user->is_admin) {
                $v = $this->validator($data, $user->id, $trip_id);
                if ($v->fails()) {
                    $this->setErrors($v->errors());

                    return;
                } else {
                    if (isset($data['total_seats']) && $data['total_seats'] < $trip->passengerAccepted()->count()) {
                        $this->setErrors(['error' => 'trip_invalid_seats']);

                        return;
                    }

                    $trip = $this->tripRepo->update($trip, $data);
                    event(new UpdateEvent($trip));

                    return $trip;
                }
            } else {
                $this->setErrors(trans('errors.tripowner'));

                return;
            }
        } else {
            $this->setErrors(trans('errors.notrip'));

            return;
        }
    }

    public function changeTripSeats($user, $trip_id, $increment)
    {
        $trip = $this->tripRepo->show($trip_id);
        if ($trip) {
            if ($user->id == $trip->user->id || $user->is_admin) {
                $data = [];
                $data['total_seats'] = $trip->total_seats + $increment;
                if ($data['total_seats'] < 0) {
                    $this->setErrors(['error' => 'trip_seats_greater_than_zero']);

                    return;
                }
                if ($data['total_seats'] > 4) {
                    $this->setErrors(['error' => 'trip_seats_less_than_four']);

                    return;
                }
                if ($data['total_seats'] < $trip->passengerAccepted()->count()) {
                    $this->setErrors(['error' => 'trip_invalid_seats']);

                    return;
                }
                $trip = $this->tripRepo->update($trip, $data);

                return $trip;
            } else {
                $this->setErrors(trans('errors.tripowner'));

                return;
            }
        } else {
            $this->setErrors(trans('errors.notrip'));

            return;
        }
    }

    public static function exist($trip_id)
    {
        return true;
    }

    public function delete($user, $trip_id)
    {
        $trip = $this->tripRepo->show($trip_id);
        if ($trip) {
            // [TODO] Agregar lógica de pasajeros
            if ($user->id == $trip->user->id || $user->is_admin) {
                event(new DeleteEvent($trip));

                return $this->tripRepo->delete($trip);
            } else {
                $this->setErrors(trans('errors.tripowner'));

                return;
            }
        } else {
            $this->setErrors(trans('errors.notrip'));

            return;
        }
    }

    public function show($user, $trip_id)
    {
        $trip = $this->tripRepo->show($user, $trip_id);
        if ($this->userCanSeeTrip($user, $trip)) {
            return $trip;
        } else {
            $this->setErrors(['error' => 'trip_not_foound']);
            return;
        }
    }

    public function index($data)
    {
        return $this->tripRepo->search($user, $data);
    }

    public function search($user, $data)
    {
        return $this->tripRepo->search($user, $data);
    }

    public function getTrips($user, $userId, $asDriver)
    {
        return $this->tripRepo->getTrips($user, $userId, $asDriver);
    }

    public function getOldTrips($user, $userId, $asDriver)
    {
        return $this->tripRepo->getOldTrips($user, $userId, $asDriver);
    }

    public function tripOwner($user, $trip)
    {
        $trip = $trip instanceof Model ? $trip : $this->tripRepo->show($trip);
        if ($trip) {
            return $trip->user_id == $user->id || $user->is_admin;
        }

        return false;
    }

    public function price($from, $to, $distance) 
    {
        if ($from && $to && config('carpoolear.api_price'))
        {
            return $this->calcTripPrice($from, $to, $distance);
        } else {
            return $this->tripRepo->simplePrice($distance);
        }
    }

    public function userCanSeeTrip($user, $trip)
    {
        $friendsManager = \App::make('\STS\Contracts\Logic\Friends');
        if (is_int($trip)) {
            $trip = $this->tripRepo->show($trip);
        }

        if (! $trip) {
            return false;
        }

        if ($user->id == $trip->user->id) {
            return true;
        }

        if ($trip->friendship_type_id == Trip::PRIVACY_PUBLIC) {
            return true;
        }

        if ($trip->friendship_type_id == Trip::PRIVACY_FRIENDS) {
            return $friendsManager->areFriend($user, $trip->user);
        }

        if ($trip->friendship_type_id == Trip::PRIVACY_FOF) {
            return $friendsManager->areFriend($user, $trip->user, true);
        }

        // [TODO] Faltaría saber si sos pasajero

        return false;
    }

    public function shareTrip ($me, $user) {
        return ($this->tripRepo->shareTrip($me, $user) || $this->tripRepo->shareTrip($user, $me));
    }
    
    public function getTripByTripPassenger ($transaction_id) 
    {
        return $this->tripRepo->getTripByTripPassenger($transaction_id);
    }

    public function calcTripPrice ($from, $to, $distance) {
        $country = config('carpoolear.osm_country', '');
        if (config('carpoolear.osm_country', '') == 'ARG') {
            return $this->tripRepo->simplePrice($distance);
        } else if ($country == 'CHL') {
            $ciudades = json_decode('{"destinations":[{"slug":"achao","title":"Achao","type":"Chiletur::City"},{"slug":"algarrobito","title":"Algarrobito","type":"Chiletur::City"},{"slug":"algarrobo-y-el-quisco","title":"Algarrobo y El Quisco","type":"Chiletur::City"},{"slug":"alto-del-carmen","title":"Alto del Carmen","type":"Chiletur::City"},{"slug":"alto-hospicio","title":"Alto Hospicio","type":"Chiletur::City"},{"slug":"ancud","title":"Ancud","type":"Chiletur::City"},{"slug":"andacollo","title":"Andacollo","type":"Chiletur::City"},{"slug":"angol","title":"Angol","type":"Chiletur::City"},{"slug":"angostura-del-biobio","title":"Angostura del Biob\u00edo","type":"Chiletur::City"},{"slug":"antofagasta","title":"Antofagasta","type":"Chiletur::City"},{"slug":"antuco","title":"Antuco","type":"Chiletur::City"},{"slug":"arauco","title":"Arauco","type":"Chiletur::City"},{"slug":"arica","title":"Arica","type":"Chiletur::City"},{"slug":"asedfgh","title":"asedfgh","type":"Chiletur::City"},{"slug":"ayquina","title":"Ayquina","type":"Chiletur::City"},{"slug":"bahia-inglesa","title":"Bah\u00eda Inglesa","type":"Chiletur::City"},{"slug":"bahia-mansa-y-maicolpue","title":"Bah\u00eda Mansa y Maicolpu\u00e9","type":"Chiletur::City"},{"slug":"balmaceda","title":"Balmaceda","type":"Chiletur::City"},{"slug":"baquedano","title":"Baquedano","type":"Chiletur::City"},{"slug":"belen","title":"Bel\u00e9n","type":"Chiletur::City"},{"slug":"bulnes","title":"Bulnes","type":"Chiletur::City"},{"slug":"cabildo","title":"Cabildo","type":"Chiletur::City"},{"slug":"cachagua","title":"Cachagua","type":"Chiletur::City"},{"slug":"cahuil","title":"C\u00e1huil","type":"Chiletur::City"},{"slug":"cajon-del-maipo","title":"Caj\u00f3n del Maipo","type":"Chiletur::City"},{"slug":"calama","title":"Calama","type":"Chiletur::City"},{"slug":"calbuco","title":"Calbuco","type":"Chiletur::City"},{"slug":"caldera","title":"Caldera","type":"Chiletur::City"},{"slug":"caleta-tortel","title":"Caleta Tortel","type":"Chiletur::City"},{"slug":"caleu","title":"Caleu","type":"Chiletur::City"},{"slug":"cameron","title":"Cameron","type":"Chiletur::City"},{"slug":"camina","title":"Cami\u00f1a","type":"Chiletur::City"},{"slug":"canela-baja","title":"Canela Baja","type":"Chiletur::City"},{"slug":"canete","title":"Ca\u00f1ete","type":"Chiletur::City"},{"slug":"capitan-pastene","title":"Capit\u00e1n Pastene","type":"Chiletur::City"},{"slug":"caquena","title":"Caquena ","type":"Chiletur::City"},{"slug":"caracoles","title":"Caracoles","type":"Chiletur::City"},{"slug":"carahue","title":"Carahue","type":"Chiletur::City"},{"slug":"carelmapu","title":"Carelmapu","type":"Chiletur::City"},{"slug":"cartagena","title":"Cartagena","type":"Chiletur::City"},{"slug":"casablanca","title":"Casablanca","type":"Chiletur::City"},{"slug":"caspana","title":"Caspana","type":"Chiletur::City"},{"slug":"castro","title":"Castro","type":"Chiletur::City"},{"slug":"cauquenes","title":"Cauquenes","type":"Chiletur::City"},{"slug":"cerro-sombrero","title":"Cerro Sombrero","type":"Chiletur::City"},{"slug":"chaiten","title":"Chait\u00e9n","type":"Chiletur::City"},{"slug":"chanaral","title":"Cha\u00f1aral","type":"Chiletur::City"},{"slug":"chanaral-alto","title":"Cha\u00f1aral Alto","type":"Chiletur::City"},{"slug":"chanco","title":"Chanco","type":"Chiletur::City"},{"slug":"chile-chico","title":"Chile Chico","type":"Chiletur::City"},{"slug":"chillan","title":"Chill\u00e1n","type":"Chiletur::City"},{"slug":"chimbarongo","title":"Chimbarongo","type":"Chiletur::City"},{"slug":"chiu-chiu","title":"Chiu Chiu ","type":"Chiletur::City"},{"slug":"cholchol","title":"Cholchol ","type":"Chiletur::City"},{"slug":"chonchi","title":"Chonchi","type":"Chiletur::City"},{"slug":"choshuenco","title":"Choshuenco","type":"Chiletur::City"},{"slug":"cobquecura","title":"Cobquecura","type":"Chiletur::City"},{"slug":"cochamo","title":"Cocham\u00f3","type":"Chiletur::City"},{"slug":"cochrane","title":"Cochrane","type":"Chiletur::City"},{"slug":"codpa","title":"Codpa","type":"Chiletur::City"},{"slug":"coihueco","title":"Coihueco","type":"Chiletur::City"},{"slug":"colbun","title":"Colb\u00fan","type":"Chiletur::City"},{"slug":"colchane","title":"Colchane","type":"Chiletur::City"},{"slug":"collipulli","title":"Collipulli","type":"Chiletur::City"},{"slug":"combarbala","title":"Combarbal\u00e1","type":"Chiletur::City"},{"slug":"conaripe","title":"Co\u00f1aripe","type":"Chiletur::City"},{"slug":"concepcion","title":"Concepci\u00f3n","type":"Chiletur::City"},{"slug":"constitucion","title":"Constituci\u00f3n","type":"Chiletur::City"},{"slug":"contulmo","title":"Contulmo","type":"Chiletur::City"},{"slug":"copiapo","title":"Copiap\u00f3","type":"Chiletur::City"},{"slug":"coquimbo","title":"Coquimbo","type":"Chiletur::City"},{"slug":"coronel-y-lota","title":"Coronel y Lota","type":"Chiletur::City"},{"slug":"coyhaique","title":"Coyhaique","type":"Chiletur::City"},{"slug":"cucao","title":"Cucao","type":"Chiletur::City"},{"slug":"cumpeo","title":"Cumpeo","type":"Chiletur::City"},{"slug":"cunco-y-melipeuco","title":"Cunco y Melipeuco","type":"Chiletur::City"},{"slug":"cuncumen","title":"Cuncum\u00e9n (IV Regi\u00f3n)","type":"Chiletur::City"},{"slug":"curacautin-y-lonquimay","title":"Curacaut\u00edn y Lonquimay","type":"Chiletur::City"},{"slug":"curaco-de-velez","title":"Curaco de V\u00e9lez","type":"Chiletur::City"},{"slug":"curanilahue","title":"Curanilahue","type":"Chiletur::City"},{"slug":"curanipe","title":"Curanipe","type":"Chiletur::City"},{"slug":"curarrehue","title":"Curarrehue","type":"Chiletur::City"},{"slug":"curepto","title":"Curepto","type":"Chiletur::City"},{"slug":"curico","title":"Curic\u00f3","type":"Chiletur::City"},{"slug":"dalcahue","title":"Dalcahue","type":"Chiletur::City"},{"slug":"dichato","title":"Dichato","type":"Chiletur::City"},{"slug":"donihue","title":"Do\u00f1ihue","type":"Chiletur::City"},{"slug":"duao-e-iloca","title":"Duao e Iloca","type":"Chiletur::City"},{"slug":"el-almendral","title":"El Almendral","type":"Chiletur::City"},{"slug":"el-palqui","title":"El Palqui","type":"Chiletur::City"},{"slug":"el-salvador","title":"El Salvador","type":"Chiletur::City"},{"slug":"el-tabo","title":"El Tabo","type":"Chiletur::City"},{"slug":"el-totoral","title":"El Totoral","type":"Chiletur::City"},{"slug":"enquelga","title":"Enquelga","type":"Chiletur::City"},{"slug":"ensenada","title":"Ensenada","type":"Chiletur::City"},{"slug":"entre-lagos","title":"Entre Lagos","type":"Chiletur::City"},{"slug":"farellones","title":"Farellones","type":"Chiletur::City"},{"slug":"freire","title":"Freire","type":"Chiletur::City"},{"slug":"freirina","title":"Freirina","type":"Chiletur::City"},{"slug":"frutillar","title":"Frutillar","type":"Chiletur::City"},{"slug":"futaleufu","title":"Futaleuf\u00fa","type":"Chiletur::City"},{"slug":"futrono-y-llifen","title":"Futrono y Llif\u00e9n","type":"Chiletur::City"},{"slug":"galvarino","title":"Galvarino","type":"Chiletur::City"},{"slug":"gorbea","title":"Gorbea","type":"Chiletur::City"},{"slug":"graneros","title":"Graneros","type":"Chiletur::City"},{"slug":"grupo-chauques","title":"Grupo Islas Chauques","type":"Chiletur::City"},{"slug":"grupo-islas-quehui","title":"Grupo Islas Quehui","type":"Chiletur::City"},{"slug":"grupo-islas-quenac","title":"Grupo Islas Quenac","type":"Chiletur::City"},{"slug":"guacarhue","title":"Guacarhue","type":"Chiletur::City"},{"slug":"guallatire","title":"Guallatire","type":"Chiletur::City"},{"slug":"guanacagua","title":"Gua\u00f1acagua","type":"Chiletur::City"},{"slug":"guanaqueros-y-tongoy","title":"Guanaqueros y Tongoy","type":"Chiletur::City"},{"slug":"guatulame","title":"Guatulame","type":"Chiletur::City"},{"slug":"horcon","title":"Horc\u00f3n","type":"Chiletur::City"},{"slug":"horcon-iv-region","title":"Horc\u00f3n (IV Regi\u00f3n)","type":"Chiletur::City"},{"slug":"hornopiren","title":"Hornopir\u00e9n","type":"Chiletur::City"},{"slug":"huara","title":"Huara","type":"Chiletur::City"},{"slug":"huasco","title":"Huasco","type":"Chiletur::City"},{"slug":"huatacondo","title":"Huatacondo","type":"Chiletur::City"},{"slug":"huavina","title":"Huavi\u00f1a","type":"Chiletur::City"},{"slug":"huentelauquen","title":"Huentelauqu\u00e9n","type":"Chiletur::City"},{"slug":"illapel","title":"Illapel","type":"Chiletur::City"},{"slug":"iquique","title":"Iquique","type":"Chiletur::City"},{"slug":"isla-de-maipo","title":"Isla de Maipo","type":"Chiletur::City"},{"slug":"isla-de-pascua","title":"Isla de Pascua","type":"Chiletur::City"},{"slug":"isla-negra","title":"Isla Negra","type":"Chiletur::City"},{"slug":"isla-robinson-crusoe","title":"Isla Robinson Crusoe","type":"Chiletur::City"},{"slug":"isluga","title":"Isluga","type":"Chiletur::City"},{"slug":"la-cruz","title":"La Cruz","type":"Chiletur::City"},{"slug":"la-huayca","title":"La Huayca","type":"Chiletur::City"},{"slug":"la-junta","title":"La Junta","type":"Chiletur::City"},{"slug":"la-ligua","title":"La Ligua","type":"Chiletur::City"},{"slug":"la-paz","title":"La Paz (Bolivia)","type":"Chiletur::City"},{"slug":"la-serena","title":"La Serena","type":"Chiletur::City"},{"slug":"la-tirana","title":"La Tirana","type":"Chiletur::City"},{"slug":"la-union","title":"La Uni\u00f3n","type":"Chiletur::City"},{"slug":"lago-budi-e-isla-llepo","title":"Lago Budi e Isla Llepo","type":"Chiletur::City"},{"slug":"lago-llanquihue","title":"Lago Llanquihue","type":"Chiletur::City"},{"slug":"lago-ranco","title":"Lago Ranco","type":"Chiletur::City"},{"slug":"lago-verde","title":"Lago Verde","type":"Chiletur::City"},{"slug":"lago-vichuquen","title":"Lago Vichuqu\u00e9n","type":"Chiletur::City"},{"slug":"laguna-verde","title":"Laguna Verde","type":"Chiletur::City"},{"slug":"laraquete","title":"Laraquete","type":"Chiletur::City"},{"slug":"las-cabras","title":"Las Cabras","type":"Chiletur::City"},{"slug":"las-cruces","title":"Las Cruces","type":"Chiletur::City"},{"slug":"lautaro","title":"Lautaro","type":"Chiletur::City"},{"slug":"lebu","title":"Lebu","type":"Chiletur::City"},{"slug":"lican-ray","title":"Lican Ray","type":"Chiletur::City"},{"slug":"limache","title":"Limache","type":"Chiletur::City"},{"slug":"linares","title":"Linares","type":"Chiletur::City"},{"slug":"liquine","title":"Liqui\u00f1e","type":"Chiletur::City"},{"slug":"lirquen","title":"Lirqu\u00e9n","type":"Chiletur::City"},{"slug":"litueche","title":"Litueche","type":"Chiletur::City"},{"slug":"llanquihue","title":"Llanquihue","type":"Chiletur::City"},{"slug":"lolol","title":"Lolol","type":"Chiletur::City"},{"slug":"loncoche","title":"Loncoche","type":"Chiletur::City"},{"slug":"los-andes-y-san-felipe","title":"Los Andes y San Felipe","type":"Chiletur::City"},{"slug":"los-angeles","title":"Los \u00c1ngeles","type":"Chiletur::City"},{"slug":"los-antiguos-argentina","title":"Los Antiguos (Argentina)","type":"Chiletur::City"},{"slug":"los-lagos","title":"Los Lagos","type":"Chiletur::City"},{"slug":"los-molles","title":"Los Molles","type":"Chiletur::City"},{"slug":"los-quenes","title":"Los Que\u00f1es","type":"Chiletur::City"},{"slug":"los-vilos","title":"Los Vilos","type":"Chiletur::City"},{"slug":"macaya","title":"Macaya","type":"Chiletur::City"},{"slug":"machali","title":"Machal\u00ed","type":"Chiletur::City"},{"slug":"maipo","title":"Maipo","type":"Chiletur::City"},{"slug":"maitencillo","title":"Maitencillo","type":"Chiletur::City"},{"slug":"malloa","title":"Malloa","type":"Chiletur::City"},{"slug":"mamina","title":"Mami\u00f1a","type":"Chiletur::City"},{"slug":"marchigue","title":"Marchig\u00fce","type":"Chiletur::City"},{"slug":"maria-elena","title":"Mar\u00eda Elena","type":"Chiletur::City"},{"slug":"matanzas","title":"Matanzas","type":"Chiletur::City"},{"slug":"matilla","title":"Matilla","type":"Chiletur::City"},{"slug":"maullin","title":"Maull\u00edn","type":"Chiletur::City"},{"slug":"mehuin","title":"Mehu\u00edn","type":"Chiletur::City"},{"slug":"mejillones","title":"Mejillones","type":"Chiletur::City"},{"slug":"melinka","title":"Melinka","type":"Chiletur::City"},{"slug":"melipilla","title":"Melipilla","type":"Chiletur::City"},{"slug":"mendoza","title":"Mendoza (Argentina)","type":"Chiletur::City"},{"slug":"mirasol","title":"Mirasol","type":"Chiletur::City"},{"slug":"molina","title":"Molina","type":"Chiletur::City"},{"slug":"monte-patria","title":"Monte Patria","type":"Chiletur::City"},{"slug":"montegrande","title":"Montegrande","type":"Chiletur::City"},{"slug":"mulchen","title":"Mulch\u00e9n","type":"Chiletur::City"},{"slug":"nacimiento","title":"Nacimiento","type":"Chiletur::City"},{"slug":"nancagua","title":"Nancagua","type":"Chiletur::City"},{"slug":"navidad","title":"Navidad","type":"Chiletur::City"},{"slug":"niebla-y-corral","title":"Niebla y Corral","type":"Chiletur::City"},{"slug":"nueva-imperial","title":"Nueva Imperial","type":"Chiletur::City"},{"slug":"olmue","title":"Olmu\u00e9","type":"Chiletur::City"},{"slug":"osorno","title":"Osorno","type":"Chiletur::City"},{"slug":"ovalle","title":"Ovalle","type":"Chiletur::City"},{"slug":"o-rongo","title":"O` Rongo","type":"Chiletur::City"},{"slug":"paihuano","title":"Paihuano","type":"Chiletur::City"},{"slug":"paine","title":"Paine","type":"Chiletur::City"},{"slug":"palena","title":"Palena","type":"Chiletur::City"},{"slug":"panguipulli","title":"Panguipulli","type":"Chiletur::City"},{"slug":"parca","title":"Parca","type":"Chiletur::City"},{"slug":"parinacota","title":"Parinacota","type":"Chiletur::City"},{"slug":"parral","title":"Parral","type":"Chiletur::City"},{"slug":"pelluhue","title":"Pelluhue","type":"Chiletur::City"},{"slug":"penaflor","title":"Pe\u00f1aflor","type":"Chiletur::City"},{"slug":"penco","title":"Penco","type":"Chiletur::City"},{"slug":"petorca","title":"Petorca","type":"Chiletur::City"},{"slug":"petrohue","title":"Petrohu\u00e9","type":"Chiletur::City"},{"slug":"peulla","title":"Peulla","type":"Chiletur::City"},{"slug":"peumo","title":"Peumo","type":"Chiletur::City"},{"slug":"pica","title":"Pica","type":"Chiletur::City"},{"slug":"pichicuy","title":"Pichicuy","type":"Chiletur::City"},{"slug":"pichidangui","title":"Pichidangui","type":"Chiletur::City"},{"slug":"pichidegua","title":"Pichidegua","type":"Chiletur::City"},{"slug":"pichilemu","title":"Pichilemu","type":"Chiletur::City"},{"slug":"pirque","title":"Pirque ","type":"Chiletur::City"},{"slug":"pisco-elqui","title":"Pisco Elqui","type":"Chiletur::City"},{"slug":"pitrufquen","title":"Pitrufqu\u00e9n","type":"Chiletur::City"},{"slug":"poconchile","title":"Poconchile","type":"Chiletur::City"},{"slug":"pocuro","title":"Pocuro","type":"Chiletur::City"},{"slug":"pomaire","title":"Pomaire","type":"Chiletur::City"},{"slug":"portillo","title":"Portillo","type":"Chiletur::City"},{"slug":"porvenir","title":"Porvenir","type":"Chiletur::City"},{"slug":"pozo-almonte","title":"Pozo Almonte","type":"Chiletur::City"},{"slug":"pucon","title":"Puc\u00f3n","type":"Chiletur::City"},{"slug":"puerto-aysen","title":"Puerto Ays\u00e9n","type":"Chiletur::City"},{"slug":"puerto-bertrand","title":"Puerto Bertrand","type":"Chiletur::City"},{"slug":"puerto-chacabuco","title":"Puerto Chacabuco","type":"Chiletur::City"},{"slug":"puerto-cisnes","title":"Puerto Cisnes","type":"Chiletur::City"},{"slug":"puerto-eden","title":"Puerto Ed\u00e9n","type":"Chiletur::City"},{"slug":"puerto-fuy","title":"Puerto Fuy","type":"Chiletur::City"},{"slug":"puerto-guadal","title":"Puerto Guadal","type":"Chiletur::City"},{"slug":"puerto-ingeniero-ibanez","title":"Puerto Ingeniero Ib\u00e1\u00f1ez","type":"Chiletur::City"},{"slug":"puerto-montt","title":"Puerto Montt","type":"Chiletur::City"},{"slug":"puerto-murta","title":"Puerto Murta","type":"Chiletur::City"},{"slug":"puerto-natales","title":"Puerto Natales","type":"Chiletur::City"},{"slug":"puerto-octay","title":"Puerto Octay","type":"Chiletur::City"},{"slug":"puerto-saavedra","title":"Puerto Saavedra ","type":"Chiletur::City"},{"slug":"puerto-varas","title":"Puerto Varas","type":"Chiletur::City"},{"slug":"puerto-williams","title":"Puerto Williams","type":"Chiletur::City"},{"slug":"punitaqui","title":"Punitaqui","type":"Chiletur::City"},{"slug":"punta-arenas","title":"Punta Arenas","type":"Chiletur::City"},{"slug":"punta-de-choros-e-isla-damas","title":"Punta de Choros e Isla Damas","type":"Chiletur::City"},{"slug":"punta-de-tralca","title":"Punta de Tralca","type":"Chiletur::City"},{"slug":"puren","title":"Pur\u00e9n","type":"Chiletur::City"},{"slug":"putaendo","title":"Putaendo","type":"Chiletur::City"},{"slug":"putre","title":"Putre","type":"Chiletur::City"},{"slug":"puyuhuapi","title":"Puyuhuapi","type":"Chiletur::City"},{"slug":"quellon","title":"Quell\u00f3n","type":"Chiletur::City"},{"slug":"quilaco","title":"Quilaco","type":"Chiletur::City"},{"slug":"quilimari","title":"Quilimar\u00ed","type":"Chiletur::City"},{"slug":"quillon","title":"Quill\u00f3n","type":"Chiletur::City"},{"slug":"quillota","title":"Quillota","type":"Chiletur::City"},{"slug":"quilpue","title":"Quilpu\u00e9","type":"Chiletur::City"},{"slug":"quintay","title":"Quintay","type":"Chiletur::City"},{"slug":"quintero","title":"Quintero","type":"Chiletur::City"},{"slug":"quirihue","title":"Quirihue","type":"Chiletur::City"},{"slug":"rancagua","title":"Rancagua","type":"Chiletur::City"},{"slug":"rapel","title":"Rapel","type":"Chiletur::City"},{"slug":"renaca-y-concon","title":"Re\u00f1aca y Concon","type":"Chiletur::City"},{"slug":"rengo","title":"Rengo","type":"Chiletur::City"},{"slug":"rinconada-de-silva","title":"Rinconada de Silva","type":"Chiletur::City"},{"slug":"rio-bueno","title":"R\u00edo Bueno","type":"Chiletur::City"},{"slug":"rocas-de-santo-domingo","title":"Rocas de Santo Domingo","type":"Chiletur::City"},{"slug":"romeral","title":"Romeral","type":"Chiletur::City"},{"slug":"salamanca","title":"Salamanca","type":"Chiletur::City"},{"slug":"san-alfonso","title":"San Alfonso","type":"Chiletur::City"},{"slug":"san-antonio","title":"San Antonio","type":"Chiletur::City"},{"slug":"san-carlos-de-bariloche","title":"San Carlos de Bariloche","type":"Chiletur::City"},{"slug":"san-clemente","title":"San Clemente","type":"Chiletur::City"},{"slug":"san-fabian-de-alico","title":"San Fabi\u00e1n de Alico","type":"Chiletur::City"},{"slug":"san-fernando","title":"San Fernando","type":"Chiletur::City"},{"slug":"san-jose-de-maipo","title":"San Jos\u00e9 de Maipo","type":"Chiletur::City"},{"slug":"san-juan-bautista","title":"San Juan Bautista","type":"Chiletur::City"},{"slug":"san-juan-de-la-costa","title":"San Juan de la Costa ","type":"Chiletur::City"},{"slug":"san-lorenzo-de-tarapaca","title":"San Lorenzo de Tarapac\u00e1","type":"Chiletur::City"},{"slug":"san-martin-de-los-andes","title":"San Mart\u00edn de Los Andes (Argentina)","type":"Chiletur::City"},{"slug":"san-miguel-de-azapa","title":"San Miguel de Azapa","type":"Chiletur::City"},{"slug":"san-pedro-de-alcantara","title":"San Pedro de Alc\u00e1ntara","type":"Chiletur::City"},{"slug":"san-pedro-de-atacama","title":"San Pedro de Atacama","type":"Chiletur::City"},{"slug":"san-sebastian","title":"San Sebasti\u00e1n","type":"Chiletur::City"},{"slug":"san-vicente-de-tagua-tagua","title":"San Vicente de Tagua Tagua","type":"Chiletur::City"},{"slug":"santa-barbara","title":"Santa B\u00e1rbara","type":"Chiletur::City"},{"slug":"santa-cruz","title":"Santa Cruz","type":"Chiletur::City"},{"slug":"santiago","title":"Santiago","type":"Chiletur::City"},{"slug":"sibaya","title":"Sibaya","type":"Chiletur::City"},{"slug":"sierra-gorda","title":"Sierra Gorda","type":"Chiletur::City"},{"slug":"socaire","title":"Socaire","type":"Chiletur::City"},{"slug":"socoroma","title":"Socoroma","type":"Chiletur::City"},{"slug":"sotaqui","title":"Sotaqu\u00ed","type":"Chiletur::City"},{"slug":"tacna-y-arequipa","title":"Tacna y Arequipa","type":"Chiletur::City"},{"slug":"talagante","title":"Talagante","type":"Chiletur::City"},{"slug":"talca","title":"Talca","type":"Chiletur::City"},{"slug":"talcahuano","title":"Talcahuano","type":"Chiletur::City"},{"slug":"taltal","title":"Taltal","type":"Chiletur::City"},{"slug":"temuco","title":"Temuco","type":"Chiletur::City"},{"slug":"teodoro-schmidt","title":"Teodoro Schmidt","type":"Chiletur::City"},{"slug":"tierra-amarilla","title":"Tierra Amarilla","type":"Chiletur::City"},{"slug":"tignamar","title":"Tignamar","type":"Chiletur::City"},{"slug":"til-til","title":"Til Til","type":"Chiletur::City"},{"slug":"tirua","title":"Tir\u00faa","type":"Chiletur::City"},{"slug":"toconao","title":"Toconao","type":"Chiletur::City"},{"slug":"tocopilla","title":"Tocopilla","type":"Chiletur::City"},{"slug":"tome","title":"Tom\u00e9","type":"Chiletur::City"},{"slug":"totoralillo","title":"Totoralillo","type":"Chiletur::City"},{"slug":"traiguen","title":"Traigu\u00e9n","type":"Chiletur::City"},{"slug":"tunquen","title":"Tunqu\u00e9n","type":"Chiletur::City"},{"slug":"usmagama","title":"Usmagama","type":"Chiletur::City"},{"slug":"valdivia","title":"Valdivia","type":"Chiletur::City"},{"slug":"valle-de-curacavi","title":"Valle de Curacav\u00ed","type":"Chiletur::City"},{"slug":"valle-las-trancas","title":"Valle Las Trancas","type":"Chiletur::City"},{"slug":"vallenar","title":"Vallenar","type":"Chiletur::City"},{"slug":"valparaiso","title":"Valpara\u00edso","type":"Chiletur::City"},{"slug":"vichuquen","title":"Vichuqu\u00e9n","type":"Chiletur::City"},{"slug":"victoria","title":"Victoria","type":"Chiletur::City"},{"slug":"vicuna","title":"Vicu\u00f1a","type":"Chiletur::City"},{"slug":"villa-alegre","title":"Villa Alegre","type":"Chiletur::City"},{"slug":"villa-cerro-castillo","title":"Villa Cerro Castillo (XII regi\u00f3n de Magallanes y de la Ant\u00e1rtica Chilena)","type":"Chiletur::City"},{"slug":"villa-manihuales","title":"Villa Ma\u00f1ihuales","type":"Chiletur::City"},{"slug":"villa-o-higgins","title":"Villa O`Higgins","type":"Chiletur::City"},{"slug":"villarrica","title":"Villarrica","type":"Chiletur::City"},{"slug":"vina-del-mar","title":"Vi\u00f1a del Mar","type":"Chiletur::City"},{"slug":"visviri","title":"Visviri","type":"Chiletur::City"},{"slug":"yerbas-buenas","title":"Yerbas Buenas","type":"Chiletur::City"},{"slug":"yumbel","title":"Yumbel","type":"Chiletur::City"},{"slug":"yungay","title":"Yungay","type":"Chiletur::City"},{"slug":"zapallar-y-papudo","title":"Zapallar y Papudo","type":"Chiletur::City"},{"slug":"zuniga","title":"Z\u00fa\u00f1iga","type":"Chiletur::City"}]}');
            // $ciudades->destinations
            $slug_origin = '';
            $slug_destiny = '';
            foreach ($ciudades->destinations as $ciudad) {
                if (strpos($from['name'], $ciudad->title) !== false) {
                    $slug_origin = $ciudad->slug;
                }
                if (strpos($to['name'], $ciudad->title) !== false) {
                    $slug_destiny = $ciudad->slug;
                }
                if (!empty($slug_destiny) && !empty($slug_origin)) {
                    break;
                }
            }

            if (!empty($slug_destiny) && !empty($slug_origin)) {
                $url = 'https://ww2.copec.cl/chiletur/planner_route.json?start_destination=' . $slug_origin . '&end_destination=' . $slug_destiny;
        
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                $output = curl_exec($ch);
                curl_close($ch);
        
                $calc = json_decode($output);
                if (!isset($calc->error)) {
                    $price_pretol = $calc->combustible->default_gasoline_value * ($calc->distance / 1000) / 14; // 14 lts por km en ruta
                    $price_tolls = 0;
                    foreach ($calc->tolls as $toll) {
                        $price_tolls += $toll->car_valley;
                    }
                    // $response = new \stdClass();
                    // $response->total = $price_pretol + $price_tolls;
                    // $response->price_pretol = $price_pretol;
                    // $response->price_tolls = $price_tolls;
                    // $response->tolls = $calc->tolls;
                    $response = $price_pretol + $price_tolls;
                    return $response;
                }
            }
            // Llegue acá sin precio, voy con simplePrice
            return $this->tripRepo->simplePrice($distance);
        }
    }
}
