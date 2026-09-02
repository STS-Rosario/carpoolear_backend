<?php

namespace STS\Http\Controllers\Api\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use STS\Http\Controllers\Controller;
use STS\Models\ManualIdentityValidation;
use STS\Models\SupportTicket;
use STS\Models\User;
use STS\Services\ManualIdentityValidationDeletion;
use STS\Services\ManualIdentityValidationReviewNotifier;
use STS\Services\UserIdentityVerificationSuccessService;
use STS\Support\AdminPagination;
use STS\Support\ManualIdentityValidationSort;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ManualIdentityValidationController extends Controller
{
    public function __construct(
        private readonly ManualIdentityValidationReviewNotifier $reviewNotifier,
    ) {}

    /**
     * GET /api/admin/manual-identity-validations - list: paid first; within paid: with submitted_at (docs sent) first, then pending review, approved, rejected; then by waiting time (oldest first).
     * Waiting time = submitted_at (if submitted), else paid_at, else created_at.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ManualIdentityValidation::with('user:id,name');

        if (! $this->queryFlagIsTruthy($request->query('show_resolved'))) {
            $query->where(function ($builder) {
                $builder->whereNull('review_status')
                    ->orWhereNotIn('review_status', ManualIdentityValidation::resolvedReviewStatusAliases());
            });
        }

        ManualIdentityValidationSort::apply(
            $query,
            $request->query('sort'),
            $request->query('direction')
        );

        $perPage = AdminPagination::resolvePerPage($request->query('per_page'));
        $page = AdminPagination::resolvePage($request->query('page'));
        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $pageItems = collect($paginator->items());
        $ticketCounts = SupportTicket::countsOpenAccountVerificationByUserIds(
            $pageItems->pluck('user_id')->all()
        );

        $items = $pageItems
            ->map(function (ManualIdentityValidation $item) use ($ticketCounts) {
                $userId = (int) $item->user_id;

                return $this->serializeIndexRow(
                    $item,
                    $ticketCounts[$userId] ?? 0
                );
            })
            ->values()
            ->all();

        return response()->json([
            'data' => $items,
            'meta' => [
                'pagination' => AdminPagination::paginationMeta($paginator),
            ],
        ]);
    }

    private function serializeIndexRow(
        ManualIdentityValidation $item,
        int $openAccountVerificationTicketsCount = 0
    ): array {
        return [
            'id' => $item->id,
            'user_id' => $item->user_id,
            'user_name' => $item->user ? $item->user->name : null,
            'paid_at' => $item->paid_at ? $item->paid_at->toDateTimeString() : null,
            'submitted_at' => $item->submitted_at ? $item->submitted_at->toDateTimeString() : null,
            'manual_validation_started_at' => $item->manual_validation_started_at ? $item->manual_validation_started_at->toDateTimeString() : null,
            'paid' => $item->paid,
            'review_status' => $item->review_status,
            'has_images' => $item->hasImages(),
            'open_account_verification_tickets_count' => $openAccountVerificationTicketsCount,
        ];
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

    /**
     * GET /api/admin/manual-identity-validations/{id} - single request with user (name, nro_doc) and image URLs (for frontend to load via image endpoint)
     */
    public function show(int $id): JsonResponse
    {
        $item = ManualIdentityValidation::with('user:id,name,nro_doc', 'reviewedBy:id,name')->findOrFail($id);

        return response()->json([
            'data' => $this->buildShowPayload($item),
        ]);
    }

    private function buildShowPayload(ManualIdentityValidation $item): array
    {
        $id = $item->id;
        $baseUrl = rtrim(config('app.url'), '/');
        $imageUrl = fn ($type) => $baseUrl.'/api/admin/manual-identity-validations/'.$id.'/image/'.$type;

        return [
            'id' => $item->id,
            'user_id' => $item->user_id,
            'user_name' => $item->user ? $item->user->name : null,
            'user_nro_doc' => $item->user ? $item->user->nro_doc : null,
            'paid_at' => $item->paid_at ? $item->paid_at->toDateTimeString() : null,
            'submitted_at' => $item->submitted_at ? $item->submitted_at->toDateTimeString() : null,
            'paid' => $item->paid,
            'review_status' => $item->review_status,
            'review_note' => $item->review_note,
            'private_admin_note' => $item->private_admin_note,
            'reviewed_at' => $item->reviewed_at ? $item->reviewed_at->toDateTimeString() : null,
            'reviewed_by' => $item->reviewed_by,
            'reviewed_by_name' => $item->reviewedBy ? $item->reviewedBy->name : null,
            'front_image_url' => $item->front_image_path ? $imageUrl('front') : null,
            'back_image_url' => $item->back_image_path ? $imageUrl('back') : null,
            'selfie_image_url' => $item->selfie_image_path ? $imageUrl('selfie') : null,
            'has_images' => $item->hasImages(),
            'images_purged_at' => $item->images_purged_at ? $item->images_purged_at->toDateTimeString() : null,
            'support_tickets_count' => SupportTicket::countForUser($item->user_id),
        ];
    }

    /**
     * GET /api/admin/manual-identity-validations/{id}/image/{type} - stream image (front|back|selfie)
     */
    public function image(int $id, string $type): StreamedResponse|JsonResponse
    {
        $allowed = ['front', 'back', 'selfie'];
        if (! in_array($type, $allowed, true)) {
            return response()->json(['error' => 'Invalid image type'], 404);
        }

        $item = ManualIdentityValidation::findOrFail($id);
        $pathColumn = $type === 'front' ? 'front_image_path' : ($type === 'back' ? 'back_image_path' : 'selfie_image_path');
        $path = $item->$pathColumn;

        if (! $path || ! Storage::disk('local')->exists($path)) {
            return response()->json(['error' => 'Image not found'], 404);
        }

        $mime = Storage::disk('local')->mimeType($path) ?: 'image/jpeg';

        return response()->stream(function () use ($path) {
            $stream = Storage::disk('local')->readStream($path);
            if ($stream) {
                fpassthru($stream);
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline',
        ]);
    }

    /**
     * POST /api/admin/manual-identity-validations/{id}/review - action: approve|reject|pending, note: string (required for reject/pending, optional for approve)
     */
    public function review(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'action' => 'required|in:approve,reject,pending',
            'note' => 'required_if:action,reject,pending|nullable|string|min:1',
        ]);

        $item = ManualIdentityValidation::with('user')->findOrFail($id);

        if (! $item->paid) {
            return response()->json(['error' => 'Unpaid request cannot be reviewed'], 422);
        }

        $admin = auth()->user();
        if ($item->manual_validation_started_at === null) {
            $item->manual_validation_started_at = now();
        }
        $item->review_status = $validated['action'] === 'approve' ? 'approved' : ($validated['action'] === 'reject' ? 'rejected' : 'pending');
        $item->reviewed_by = $admin->id;
        $item->reviewed_at = now();
        $item->review_note = $validated['note'] ?? '';
        $item->save();

        $this->syncUserIdentityForReviewStatus($item->review_status, $item->user);

        if (in_array($validated['action'], ['approve', 'reject'], true)) {
            $this->reviewNotifier->notify(
                $item->user,
                $validated['action'] === 'approve' ? 'approved' : 'rejected',
            );
        }

        return response()->json(['data' => $item->fresh(['user:id,name,nro_doc'])]);
    }

    /**
     * POST /api/admin/manual-identity-validations/{id}/state - admin override for review_status and paid.
     */
    public function updateState(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'review_status' => 'sometimes|in:pending,awaiting_photos,approved,rejected,closed',
            'paid' => 'sometimes|boolean',
            'photos_submitted' => 'sometimes|boolean',
        ]);

        if (
            ! array_key_exists('review_status', $validated) &&
            ! array_key_exists('paid', $validated) &&
            ! array_key_exists('photos_submitted', $validated)
        ) {
            return response()->json(['error' => 'At least one of review_status, paid, or photos_submitted is required'], 422);
        }

        $item = ManualIdentityValidation::with('user')->findOrFail($id);

        if (array_key_exists('paid', $validated)) {
            $this->applyPaidState($item, (bool) $validated['paid']);
        }

        if (array_key_exists('photos_submitted', $validated)) {
            $this->applyPhotosSubmittedState($item, (bool) $validated['photos_submitted']);
        }

        if (array_key_exists('review_status', $validated)) {
            $item->review_status = $validated['review_status'];
            $item->reviewed_by = auth()->id();
            $item->reviewed_at = now();
            $this->syncUserIdentityForReviewStatus($validated['review_status'], $item->user);
        }

        $item->save();

        return $this->show($id);
    }

    private function applyPaidState(ManualIdentityValidation $item, bool $paid): void
    {
        if ($paid) {
            $item->markPaidAndAwaitingPhotosIfNeeded();

            return;
        }

        $item->paid = false;
    }

    private function applyPhotosSubmittedState(ManualIdentityValidation $item, bool $photosSubmitted): void
    {
        if ($photosSubmitted) {
            if ($item->submitted_at === null) {
                $item->submitted_at = now();
            }
            $item->markPendingReview();

            return;
        }

        $item->submitted_at = null;
        $this->deleteStoredPhotos($item);
        $item->markAwaitingPhotos();
    }

    private function deleteStoredPhotos(ManualIdentityValidation $item): void
    {
        foreach (['front_image_path', 'back_image_path', 'selfie_image_path'] as $col) {
            $path = $item->$col;
            if ($path && Storage::disk('local')->exists($path)) {
                Storage::disk('local')->delete($path);
            }
            $item->$col = null;
        }
    }

    private function syncUserIdentityForReviewStatus(string $reviewStatus, User $user): void
    {
        if ($reviewStatus === ManualIdentityValidation::REVIEW_STATUS_APPROVED) {
            app(UserIdentityVerificationSuccessService::class)->applyVerification($user, 'manual');

            return;
        }

        if ($reviewStatus === ManualIdentityValidation::REVIEW_STATUS_CLOSED) {
            return;
        }

        $user->identity_validated = false;
        $user->identity_validated_at = null;
        $user->identity_validation_type = null;
        $user->identity_validation_rejected_at = null;
        $user->identity_validation_reject_reason = null;
        $user->save();
    }

    /**
     * POST /api/admin/manual-identity-validations/{id}/private-note - set nullable private_admin_note (admin-only context).
     */
    public function updatePrivateNote(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'private_admin_note' => 'nullable|string',
        ]);

        $item = ManualIdentityValidation::findOrFail($id);
        $item->private_admin_note = $validated['private_admin_note'] ?? null;
        $item->save();

        return $this->show($id);
    }

    /**
     * POST /api/admin/manual-identity-validations/{id}/purge - delete only image files, clear paths
     */
    public function purge(int $id): JsonResponse
    {
        $item = ManualIdentityValidation::findOrFail($id);

        ManualIdentityValidationDeletion::purgeStoredPhotos($item);

        return response()->json(['message' => 'Photos purged', 'data' => $item->fresh()]);
    }
}
