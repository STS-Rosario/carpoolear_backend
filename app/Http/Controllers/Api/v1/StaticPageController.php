<?php

namespace STS\Http\Controllers\Api\v1;

use Illuminate\Http\Request;
use STS\Http\Controllers\Controller;
use STS\Services\StaticPageContentService;

class StaticPageController extends Controller
{
    public function show(string $page, Request $request, StaticPageContentService $staticPageContentService)
    {
        $data = $staticPageContentService->get($page);

        return json_encode($data);
    }
}
