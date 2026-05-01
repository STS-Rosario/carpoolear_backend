<?php

namespace STS\Services;

use STS\Models\TripPoint;

class GoogleDirection
{
    public function download($trip)
    {
        $this->donwloadPoint($trip, $trip->from_town);
        $this->donwloadPoint($trip, $trip->to_town);
    }

    public function donwloadPoint($trip, $adress)
    {
        $url = 'http://maps.google.com/maps/api/geocode/json?address='.urlencode($adress);

        $result = $this->fetchGeocodeJson($url);

        if (is_array($result) && ($result['status'] ?? null) == 'OK') {
            $lat = $result['results'][0]['geometry']['location']['lat'];
            $long = $result['results'][0]['geometry']['location']['lng'];
            $address_components = $result['results'][0]['address_components'];

            $address_json = [];
            foreach ($address_components as $item) {
                $nombre = $item['long_name'];

                switch ($item['types'][0]) {
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
                }
            }

            $trip->points()->save(new TripPoint([
                'address' => $adress,
                'lat' => $lat,
                'lng' => $long,
                'json_address' => $address_json,
            ]));
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function fetchGeocodeJson(string $url): ?array
    {
        $raw = @file_get_contents($url);

        if ($raw === false) {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }
}
