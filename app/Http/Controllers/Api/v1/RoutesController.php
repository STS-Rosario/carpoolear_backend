<?php

namespace STS\Http\Controllers\Api\v1;

use Illuminate\Http\Request;
use STS\Http\Controllers\Controller;
use STS\Services\Logic\RoutesManager; 

class RoutesController extends Controller
{
    protected $routesLogic;
    public function __construct(RoutesManager $routesLogic)
    {
        $this->middleware('logged', ['except' => ['autocomplete']]);
        $this->routesLogic = $routesLogic;
    }

    public function autocomplete(Request $request) 
    {
        // TODO pagination / return errors 
        $data = $request->all();
        if (!isset($data['country'])) {
            $data['country'] = 'ARG';
        }
        if (isset($data['name']) && isset($data['country']) && isset($data['multicountry'])) {
            $node = $this->routesLogic->autocomplete($data['name'], $data['country'], ($data['multicountry'] === 'true'));
            return response()->json([
                'nodes_geos' => $node
            ]);
        }
    }
}