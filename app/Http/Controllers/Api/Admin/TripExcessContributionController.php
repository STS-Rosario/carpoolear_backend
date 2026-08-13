<?php

namespace STS\Http\Controllers\Api\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use STS\Http\Controllers\Controller;
use STS\Services\Admin\TripExcessContributionListService;
use STS\Support\AdminPagination;

class TripExcessContributionController extends Controller
{
    public function __construct(
        private readonly TripExcessContributionListService $listService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = AdminPagination::resolvePerPage($request->query('per_page'));
        $page = AdminPagination::resolvePage($request->query('page'));
        $paginator = $this->listService->paginate($perPage, $page);

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'pagination' => AdminPagination::paginationMeta($paginator),
            ],
        ]);
    }
}
