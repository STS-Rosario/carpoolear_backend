<?php

namespace STS\Http\Controllers\Api\Admin;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use STS\Http\Controllers\Controller;
use STS\Models\AdminActionLog;
use STS\Support\AdminPagination;

class ActionLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = AdminActionLog::query()->with([
            'adminUser:id,name',
            'targetUser:id,name',
        ]);

        if ($request->filled('admin_user_id')) {
            $query->where('admin_user_id', (int) $request->query('admin_user_id'));
        }

        if ($request->filled('action')) {
            $query->where('action', (string) $request->query('action'));
        }

        if ($request->filled('target_user_id')) {
            $query->where('target_user_id', (int) $request->query('target_user_id'));
        }

        if ($request->filled('from')) {
            $query->where('created_at', '>=', Carbon::parse($request->query('from'))->startOfDay());
        }

        if ($request->filled('to')) {
            $query->where('created_at', '<=', Carbon::parse($request->query('to'))->endOfDay());
        }

        $perPage = AdminPagination::resolvePerPage($request->query('per_page'));
        $page = AdminPagination::resolvePage($request->query('page'));
        $paginator = $query->orderByDesc('id')->paginate($perPage, ['*'], 'page', $page);

        $items = collect($paginator->items())
            ->map(fn (AdminActionLog $row) => $this->serialize($row))
            ->values()
            ->all();

        return response()->json([
            'data' => $items,
            'meta' => [
                'pagination' => AdminPagination::paginationMeta($paginator),
            ],
        ]);
    }

    /**
     * @return array{
     *     id: int,
     *     action: string,
     *     admin_user_id: int,
     *     admin_user_name: ?string,
     *     target_user_id: ?int,
     *     target_user_name: ?string,
     *     details: array<string, mixed>,
     *     created_at: ?string
     * }
     */
    private function serialize(AdminActionLog $row): array
    {
        return [
            'id' => $row->id,
            'action' => $row->action,
            'admin_user_id' => (int) $row->admin_user_id,
            'admin_user_name' => $row->adminUser?->name,
            'target_user_id' => $row->target_user_id !== null ? (int) $row->target_user_id : null,
            'target_user_name' => $row->targetUser?->name,
            'details' => is_array($row->details) ? $row->details : [],
            'created_at' => $row->created_at?->toAtomString(),
        ];
    }
}
