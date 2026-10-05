<?php

namespace STS\Http\Controllers\Api\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use STS\Http\Controllers\Controller;
use STS\Services\Admin\DonationLedgerListService;
use STS\Support\AdminPagination;

class DonationLedgerController extends Controller
{
    public function __construct(private DonationLedgerListService $ledgerListService) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->ledgerListService->paginate(
            [
                'kind' => $request->query('kind'),
                'status' => $request->query('status'),
                'q' => $request->query('q'),
                'user_id' => $request->query('user_id'),
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
