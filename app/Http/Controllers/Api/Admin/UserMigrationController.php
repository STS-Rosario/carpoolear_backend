<?php

namespace STS\Http\Controllers\Api\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use STS\Http\Controllers\Controller;
use STS\Models\AdminActionLog;
use STS\Models\User;
use STS\Models\UserMigration;
use STS\Services\AnonymizationService;
use STS\Services\Logic\DeviceManager;
use STS\Services\UserDeletionService;
use STS\Services\UserMigrationFieldMerger;
use Throwable;

class UserMigrationController extends Controller
{
    public function __construct(
        private readonly UserDeletionService $userDeletionService,
        private readonly AnonymizationService $anonymizationService,
        private readonly DeviceManager $deviceLogic,
        private readonly UserMigrationFieldMerger $fieldMerger,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->get('per_page', 20), 1), 100);

        $paginator = UserMigration::query()
            ->with(['admin:id,name'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        $items = collect($paginator->items())->map(function (UserMigration $m) {
            return [
                'id' => $m->id,
                'admin_user_id' => $m->admin_user_id,
                'admin' => $m->admin ? [
                    'id' => $m->admin->id,
                    'name' => $m->admin->name,
                ] : null,
                'user_id_kept' => $m->user_id_kept,
                'user_id_removed' => $m->user_id_removed,
                'created_at' => $m->created_at?->toAtomString(),
            ];
        })->values()->all();

        return response()->json([
            'data' => $items,
            'meta' => [
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'total_pages' => $paginator->lastPage(),
                ],
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate(array_merge([
            'user_id_kept' => 'required|integer|exists:users,id',
            'user_id_removed' => 'required|integer|exists:users,id|different:user_id_kept',
            'field_sources' => 'sometimes|array',
        ], $this->fieldSourceRules()));

        $admin = $request->user();
        $keptId = (int) $validated['user_id_kept'];
        $removedId = (int) $validated['user_id_removed'];
        $fieldSources = $validated['field_sources'] ?? [];

        $kept = User::findOrFail($keptId);
        $removed = User::findOrFail($removedId);

        if ($bannedUser = $this->findBannedMigrationUser($kept, $removed)) {
            return response()->json([
                'message' => 'migration_banned_user',
                'user_id' => $bannedUser->id,
                'user_name' => $bannedUser->name ?? '',
            ], 422);
        }

        Artisan::call('user:update', [
            'original' => (string) $removedId,
            'new' => (string) $keptId,
        ]);

        $this->fieldMerger->apply($kept, $removed, $fieldSources);

        $removalAction = $this->removeOrAnonymize($removedId, $admin);

        $row = UserMigration::query()->create([
            'admin_user_id' => (int) $admin->id,
            'user_id_kept' => $keptId,
            'user_id_removed' => $removedId,
        ]);

        $row->load('admin:id,name');

        return response()->json([
            'data' => [
                'id' => $row->id,
                'admin_user_id' => $row->admin_user_id,
                'admin' => $row->admin ? [
                    'id' => $row->admin->id,
                    'name' => $row->admin->name,
                ] : null,
                'user_id_kept' => $row->user_id_kept,
                'user_id_removed' => $row->user_id_removed,
                'removal_action' => $removalAction,
                'created_at' => $row->created_at?->toAtomString(),
            ],
        ]);
    }

    private function findBannedMigrationUser(User $kept, User $removed): ?User
    {
        if ($kept->banned) {
            return $kept;
        }

        if ($removed->banned) {
            return $removed;
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private function fieldSourceRules(): array
    {
        $rules = [];
        foreach (UserMigrationFieldMerger::MERGEABLE_FIELDS as $field) {
            $rules['field_sources.'.$field] = 'sometimes|in:removed,kept';
        }

        return $rules;
    }

    /**
     * Mirror admin profile delete/anonymize flow on the migrated-from user.
     * Try to delete; if deletion throws (e.g. residual FK constraints), fall back to anonymize.
     * Returns the action taken: 'deleted' or 'anonymized'.
     */
    private function removeOrAnonymize(int $removedId, User $admin): string
    {
        $removed = User::find($removedId);
        if (! $removed) {
            return 'deleted';
        }

        $this->deviceLogic->logoutAllDevices($removed);

        try {
            $this->userDeletionService->deleteUser($removed);
            $this->logAdminAction($admin, $removedId, AdminActionLog::ACTION_USER_DELETE);

            return 'deleted';
        } catch (Throwable $e) {
            Log::warning('user-migration: deletion failed, falling back to anonymize', [
                'user_id' => $removedId,
                'error' => $e->getMessage(),
            ]);

            $this->anonymizationService->anonymize($removed);
            $this->logAdminAction($admin, $removedId, AdminActionLog::ACTION_USER_ANONYMIZE, [
                'fallback_reason' => $e->getMessage(),
            ]);

            return 'anonymized';
        }
    }

    /**
     * @param  array<string, mixed>  $extraDetails
     */
    private function logAdminAction(User $admin, int $targetUserId, string $action, array $extraDetails = []): void
    {
        AdminActionLog::create([
            'admin_user_id' => $admin->id,
            'action' => $action,
            'target_user_id' => $targetUserId,
            'details' => array_merge(['source' => 'user_migration'], $extraDetails),
        ]);
    }
}
