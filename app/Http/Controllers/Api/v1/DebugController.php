<?php

namespace STS\Http\Controllers\Api\v1;

use JWTAuth;
use Illuminate\Http\Request;
use STS\Http\Controllers\Controller;

class DebugController extends Controller
{
    public function __construct()
    {
    }

    public function log(Request $request)
    {
        try {
            if ($request->has('log')) {
                \Log::info('ERROR IN APP: '.$request->get('log'));
            }
        } catch (\Exception $ex) {
        }
    }
}
