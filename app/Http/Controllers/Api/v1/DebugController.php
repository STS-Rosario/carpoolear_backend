<?php

namespace STS\Http\Controllers\Api\v1;

use JWTAuth;
use Illuminate\Http\Request;
use STS\Http\Controllers\Controller;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Dingo\Api\Exception\UpdateResourceFailedException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

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
        } catch (Exception $ex) {
        }
    }
}
