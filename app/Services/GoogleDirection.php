<?php
namespace STS\Services;

use STS\Entities\Trip;
use STS\Entities\TripPoint;

class GoogleDirection
{
    public function download($trip)
    {
        $this->donwloadPoint($trip, $trip->from_town);
        $this->donwloadPoint($trip, $trip->to_town);
    }

    public function donwloadPoint($trip, $adress)
    {
        $url ='http://maps.google.com/maps/api/geocode/json?address='.urlencode($adress);

        $result = json_decode(file_get_contents($url), true);


        if ($result['status']=='OK') {
            $lat = $result['results'][0]['geometry']['location']['lat'];
            $long = $result['results'][0]['geometry']['location']['lng'];
            $address_components = $result['results'][0]['address_components'];

            $address_json = [];
            foreach ($address_components as $item) {
                $nombre = $item['long_name'];

                switch ($item["types"][0]) {
                case 'country':
                    $address_json['pais'] = $nombre;
                    break;
                case 'administrative_area_level_1':
                    $address_json['provincia'] = $nombre;
                    break;
                case 'locality':
                    $address_json['ciudad'] = $nombre;
                    break;
                case 'route':
                    $address_json['calle'] = $nombre;
                    break;
                case 'street_number':
                    $address_json['numero'] = $nombre;
                    break;
                };
            }

            $trip->points()->save(new TripPoint([
                'address' => $adress,
                'lat' => $lat,
                'lng' => $long,
                'json_address' => $address_json
            ]));



        }



    }
}

