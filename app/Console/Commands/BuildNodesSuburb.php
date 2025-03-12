<?php

namespace STS\Console\Commands;

use STS\Models\User;
use Carbon\Carbon;
use STS\Models\NodeGeo;
use Illuminate\Console\Command;
use Storage;
use GuzzleHttp\Client;

class BuildNodesSuburb extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nodesgeo:loadsuburb';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Populate nodes table from json file';

    protected $shorts_arg = [
        'PBA' => 'Buenos Aires',
        'Bs. As.' => 'Buenos Aires',
        'CA'  => 'Catamarca',
        'CTM'  => 'Catamarca',
        'CH' => 'Chaco',
        'CCO' => 'Chaco',
        'CT' => 'Chubut',	
        'CHB' => 'Chubut',	
        'CB' => 'Córdoba',
        'Cba.' => 'Córdoba',
        'CR' => 'Corrientes', 
        'Ctes.' => 'Corrientes', 
        'ER' => 'Entre Ríos',
        'ER.' => 'Entre Ríos',
        'FO' => 'Formosa',
        'FSA' => 'Formosa',
        'JY' => 'Jujuy',	
        'JJY' => 'Jujuy',	
        'LP' => 'La Pampa',
        'LR' => 'La Rioja',
        'MZ' => 'Mendoza',
        'Mza.' => 'Mendoza',
        'MI' => 'Misiones',
        'Mnes.' => 'Misiones',
        'NQ' => 'Neuquén',
        'NQN' => 'Neuquén',
        'RN' => 'Río Negro',
        'SA' => 'Salta',
        'SJ' => 'San Juan',
        'SL' => 'San Luis',
        'SC' => 'Santa Cruz',
        'SF' => 'Santa Fe',
        'Sta. Fe' => 'Santa Fe',
        'SE' => 'Santiago del Estero',
        'SDE' => 'Santiago del Estero',
        'TF' => 'Tierra del Fuego',
        'TU' => 'Tucumán'
    ];
    protected $shorts_br = [
        'AC' => 'Acre',
        'AL' => 'Alagoas',
        'AP' => 'Amapá',
        'AM' => 'Amazonas',
        'BA' => 'Bahía',
        'CE' => 'Ceará',
        'DF' => 'Distrito Federal',
        'ES' => 'Espírito Santo',
        'GO' => 'Goiás',
        'MA' => 'Maranhão',
        'MT' => 'Mato Grosso',
        'MS' => 'Mato Grosso del Sur',
        'MG' => 'Minas Gerais',
        'PA' => 'Pará',
        'PB' => 'Paraíba',
        'PR' => 'Paraná',
        'PE' => 'Pernambuco',
        'PI' => 'Piauí',
        'RJ' => 'Río de Janeiro',
        'RN' => 'Río Grande del Norte',
        'RS' => 'Río Grande del Sur	',
        'RO' => 'Rondonia',
        'RR' => 'Roraima',
        'SC' => 'Santa Catarina',
        'SP' => 'São Paulo',
        'SE' => 'Sergipe',
        'TO' => 'Tocantins'
    ];

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->dir = storage_path() . "/suburbs/";
        $this->files = scandir($this->dir);
        unset($this->files[0]);
        unset($this->files[1]);
        $this->client = new Client();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        \Log::info("COMMAND BuildNodesSuburb");
        $startNode = 0;
        $startCountry = 0;
        $endCountry = 1;
        $countryIndex = 0;
        $nodeIndex = 0;
        foreach ($this->files as $file) {
            $countryIndex += 1;

            if ($countryIndex < $startCountry) {
                $this->info($countryIndex);
                continue;
            }

            if ($countryIndex > $endCountry) {
                break;
            }

            $parts = pathinfo($file);
            $country = $parts['filename'];
            $this->info($country);
            $json = json_decode(file_get_contents($this->dir . $file), true); 
            \Log::info($json);
            $nodes = $json['elements'];
            // \Log::info("Creating: " . file_get_contents($this->dir . $file));
            // \Log::info("Creating: " . count($json['features']));break;die;
            
            if ($countryIndex == $startCountry) {
                $nodeIndex = 0;
            }
            foreach ($nodes as $feature) {
                $nodeIndex += 1;
                if ($nodeIndex < $startNode) {
                    continue;
                }
                if (isset($feature['tags']['name'])){
                    $node = new NodeGeo;
                    $node->name = $feature['tags']['name'];
                    $node->type = $feature['tags']['place'];
                    $node->lng = $feature['lat'];
                    $node->lat = $feature['lon'];
                    $node->country = $country;

                    if (empty($feature['tags']['is_in:province']) && empty($feature['tags']['is_in:state'])) {
                        sleep(1);
                        $state = $this->geocodeState($node->lat, $node->lng);
                        if ($state) {
                            $node->state = $state;
                            if (array_key_exists($node->state, $this->shorts_arg) && $country == 'ARG') {
                                $node->state = $this->shorts_arg[$node->state];
                            }
                            if (array_key_exists($node->state, $this->shorts_br) && $country == 'BRA') {
                                $node->state = $this->shorts_br[$node->state];
                            }
                        }
                        // \Log::info("Creating $node->type: $node->name - $node->state");
                    } else {
                        if (!empty($feature['tags']['is_in:province'])) {
                            $node->state = $feature['tags']['is_in:province'];
                        } else {
                            $node->state = $feature['tags']['is_in:state'];
                        }
                    }
                    $node->save();
                }
            }
        }
    }

    public function geocodeState($lat, $long) {
        $data = array('lat' => $lat, 'lon' => $long, 'format' => 'json', 'zoom' => 16);
    
        try {
            $response = $this->client->get("https://nominatim.openstreetmap.org/reverse?format=json&lat=$lat&lon=$long&zoom=8", [
                // un array con la data de los headers como tipo de peticion, etc.
                'headers' => ['user-agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/76.0.3809.132 Safari/537.36']
            ]);
        } catch (\Exception $ex) {
            \Log::info('Error on query');
            \Log::info('lat: ' . $lat . ' lng: ' . $long);
            return 0;
        }
        
        $response = $response->getBody();
        $response = json_decode($response);
        if (isset($response->address->state)) {
            return $response->address->state;
        }
        if (isset($response->address->county)) {
            \Log::info("county");
            \Log::info('lat' . $lat . ' lng' . $long);
            return $response->address->county;
        }
        return '';
        // $json = json_decode(file_get_contents("https://nominatim.openstreetmap.org/reverse?format=json&lat=$lat&lon=$long&zoom=8"), true);
    }
}
