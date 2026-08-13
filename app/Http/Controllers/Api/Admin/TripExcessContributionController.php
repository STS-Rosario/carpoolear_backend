<?php

namespace STS\Http\Controllers\Api\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use STS\Http\Controllers\Controller;
use STS\Models\Trip;
use STS\Services\Admin\TripExcessContributionListService;
use STS\Support\AdminPagination;
use STS\Support\TripExcessContributionStatus;

class TripExcessContributionController extends Controller
{
    public function __construct(
        private readonly TripExcessContributionListService $listService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = AdminPagination::resolvePerPage($request->query('per_page'));
        $page = AdminPagination::resolvePage($request->query('page'));
        $requiresActionOnly = $this->queryFlagIsTruthy($request->query('requires_action_only'));
        $paginator = $this->listService->paginate(
            $perPage,
            $page,
            $requiresActionOnly,
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

    public function show(int $id): JsonResponse
    {
        $trip = $this->listService->findForAdmin($id);

        if ($trip === null) {
            return response()->json(['error' => 'Trip not found.'], 404);
        }

        return response()->json([
            'data' => $this->listService->serializeDetail($trip),
        ]);
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => TripExcessContributionStatus::validationRule(),
        ]);

        $trip = Trip::query()
            ->where('has_potential_excess_contribution', true)
            ->whereKey($id)
            ->first();

        if ($trip === null) {
            return response()->json(['error' => 'Trip not found.'], 404);
        }

        $trip->exceso_contribucion_status = $validated['status'];
        $trip->save();

        $trip = $this->listService->findForAdmin($id);

        return response()->json([
            'data' => $trip ? $this->listService->serializeDetail($trip) : null,
        ]);
    }

    private function queryFlagIsTruthy(mixed $value): bool
    {
        if ($value === true || $value === 1) {
            return true;
        }

        if (! is_string($value)) {
            return false;
        }

        return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
    }
}
