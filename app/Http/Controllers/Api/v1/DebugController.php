<?php

namespace STS\Http\Controllers\Api\v1;

use Illuminate\Http\Request;
use STS\Http\Controllers\Controller;

class DebugController extends Controller
{
    public function log(Request $request)
    {
        if ($request->has('log')) {
            \Log::info('ERROR IN APP: '.$request->get('log'));
        }
    }
}
