<?php

namespace STS\Http\Controllers\Api\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use STS\Http\Controllers\Controller;
use STS\Services\Admin\ClubCarpoolearMembersListService;
use STS\Support\AdminPagination;

class ClubCarpoolearMembersController extends Controller
{
    public function __construct(private ClubCarpoolearMembersListService $membersListService) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->membersListService->paginate(
            [
                'status' => $request->query('status', 'current'),
                'q' => $request->query('q'),
                'tier' => $request->query('tier'),
            ],
            AdminPagination::resolvePerPage($request->query('per_page')),
            AdminPagination::resolvePage($request->query('page')),
            $request->query('sort'),
            $request->query('direction'),
        );

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'pagination' => AdminPagination::paginationMeta($paginator),
            ],
        ]);
    }
}
