<?php

namespace STS\Http\Controllers\Api\Admin;

use STS\Http\Controllers\Controller;
use STS\Http\ExceptionWithErrors;
use STS\Models\AdminActionLog;
use STS\Models\BannedUser;
use STS\Models\DeleteAccountRequest;
use STS\Models\Rating;
use STS\Models\User;
use STS\Services\AnonymizationService;
use STS\Services\Logic\DeviceManager;
use STS\Services\UserDeletionService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    protected $userDeletionService;
    protected $anonymizationService;
    protected $deviceLogic;

    public function __construct(
        UserDeletionService $userDeletionService,
        AnonymizationService $anonymizationService,
        DeviceManager $deviceLogic
    ) {
        $this->userDeletionService = $userDeletionService;
        $this->anonymizationService = $anonymizationService;
        $this->deviceLogic = $deviceLogic;
    }

    /**
     * Get a list of all delete account requests, sorted by date (newest first).
     */
    public function accountDeleteList(): JsonResponse
    {
        $requests = DeleteAccountRequest::with('user:id,name,email')
            ->orderBy('date_requested', 'desc')
            ->get();

        return response()->json(['data' => $requests]);
    }

    /**
     * Update the status of a delete account request.
     * Sets action_taken and action_taken_date to current date.
     */
    public function accountDeleteUpdate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => 'required|integer|exists:delete_account_requests,id',
            'action_taken' => [
                'required',
                'integer',
                Rule::in([
                    DeleteAccountRequest::ACTION_REQUESTED,
                    DeleteAccountRequest::ACTION_DELETED,
                    DeleteAccountRequest::ACTION_REJECTED
                ])
            ],
        ]);

        $deleteRequest = DeleteAccountRequest::findOrFail($validated['id']);

        $deleteRequest->action_taken = $validated['action_taken'];
        $deleteRequest->action_taken_date = now();
        $deleteRequest->save();

        $deleteRequest->load('user:id,name,email');

        return response()->json(['data' => $deleteRequest]);
    }

    /**
     * Clear identity validation for a user (admin only).
     * Removes identity_validated, identity_validated_at, identity_validation_type,
     * identity_validation_rejected_at, identity_validation_reject_reason.
     */
    public function clearIdentityValidation(User $user): JsonResponse
    {
        $user->identity_validated = false;
        $user->identity_validated_at = null;
        $user->identity_validation_type = null;
        $user->identity_validation_rejected_at = null;
        $user->identity_validation_reject_reason = null;
        $user->save();

        return response()->json([
            'message' => 'Identity validation cleared',
            'data' => $user->fresh(['id', 'name', 'nro_doc', 'identity_validated', 'identity_validated_at', 'identity_validation_type']),
        ]);
    }
}

    /**
     * Get list of banned users, sorted by banned_at DESC.
     */
    public function bannedUsersList(Request $request): JsonResponse
    {
        $bannedUsers = BannedUser::with('user:id,name')
            ->orderBy('banned_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json($bannedUsers);
    }

    /**
     * Delete a user (only if they have no trips, ratings, or references).
     */
    public function delete(User $user): JsonResponse
    {
        $admin = auth()->user();
        $hasTrips = $user->trips()->exists() || $user->tripsAsPassenger()->exists();
        $hasRatings = $user->ratingReceived()->exists() || $user->ratingGiven()->exists();
        $hasReferences = $user->referencesReceived()->exists();

        if ($hasTrips || $hasRatings || $hasReferences) {
            throw new ExceptionWithErrors('No se puede eliminar: el usuario tiene viajes, calificaciones o referencias.');
        }

        $targetUserId = $user->id;
        $this->deviceLogic->logoutAllDevices($user);
        $this->userDeletionService->deleteUser($user);

        AdminActionLog::create([
            'admin_user_id' => $admin->id,
            'action' => AdminActionLog::ACTION_USER_DELETE,
            'target_user_id' => $targetUserId,
            'details' => [],
        ]);

        return response()->json([
            'message' => 'Usuario eliminado exitosamente',
            'action' => 'deleted',
        ]);
    }

    /**
     * Anonymize a user. Returns 422 if user has negative ratings (use ban-and-anonymize instead).
     */
    public function anonymize(User $user): JsonResponse
    {
        $admin = auth()->user();
        $hasNegativeRatings = $user->ratings(Rating::STATE_NEGATIVO)->exists();

        if ($hasNegativeRatings) {
            return response()->json([
                'message' => 'El usuario tiene calificaciones negativas. Usar ban-and-anonymize.',
                'error' => 'requires_ban',
            ], 422);
        }

        $this->deviceLogic->logoutAllDevices($user);
        $this->anonymizationService->anonymize($user);

        AdminActionLog::create([
            'admin_user_id' => $admin->id,
            'action' => AdminActionLog::ACTION_USER_ANONYMIZE,
            'target_user_id' => $user->id,
            'details' => [],
        ]);

        return response()->json([
            'message' => 'Usuario anonimizado exitosamente',
            'action' => 'anonymized',
        ]);
    }

    /**
     * Add user to banned list (by DNI or user_id/email in note if nro_doc is null) then anonymize.
     */
    public function banAndAnonymize(Request $request, User $user): JsonResponse
    {
        $admin = auth()->user();

        $note = $request->input('note', '');
        $nroDoc = $user->nro_doc;

        if (!empty($nroDoc)) {
            $nroDoc = preg_replace('/\D/', '', (string) $nroDoc);
        }
        if (empty($nroDoc)) {
            $nroDoc = null;
        }

        if ($nroDoc === null) {
            $appendNote = 'user_id: ' . $user->id . ', email: ' . ($user->email ?? 'N/A');
            $note = $note ? $note . ' | ' . $appendNote : $appendNote;
        }

        BannedUser::create([
            'user_id' => $user->id,
            'nro_doc' => $nroDoc,
            'banned_at' => now(),
            'banned_by' => $admin->id,
            'note' => $note ?: null,
        ]);

        $this->deviceLogic->logoutAllDevices($user);
        $this->anonymizationService->anonymize($user);

        AdminActionLog::create([
            'admin_user_id' => $admin->id,
            'action' => AdminActionLog::ACTION_USER_BAN_AND_ANONYMIZE,
            'target_user_id' => $user->id,
            'details' => ['note' => $note],
        ]);

        return response()->json([
            'message' => 'Usuario bloqueado y anonimizado exitosamente',
            'action' => 'ban_and_anonymize',
        ]);
    }
}
