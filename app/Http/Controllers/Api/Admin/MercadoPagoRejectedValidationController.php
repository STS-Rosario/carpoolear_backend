<?php

namespace STS\Http\Controllers\Api\Admin;

use STS\Http\Controllers\Controller;
use STS\Models\MercadoPagoRejectedValidation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MercadoPagoRejectedValidationController extends Controller
{
    /**
     * GET /api/admin/mercado-pago-rejected-validations - list, newest first (user_id, name, nro_doc, reject_reason, created_at).
     */
    public function index(): JsonResponse
    {
        $items = MercadoPagoRejectedValidation::with('user:id,name,nro_doc,identity_validated')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'user_id' => $item->user_id,
                    'user_name' => $item->user ? $item->user->name : null,
                    'user_nro_doc' => $item->user ? $item->user->nro_doc : null,
                    'user_identity_validated' => $item->user ? (bool) $item->user->identity_validated : false,
                    'reject_reason' => $item->reject_reason,
                    'review_status' => $item->review_status,
                    'created_at' => $item->created_at->toDateTimeString(),
                ];
            });

        return response()->json(['data' => $items]);
    }

    /**
     * GET /api/admin/mercado-pago-rejected-validations/{id} - single with user and review fields (review_status, review_note, reviewed_at, reviewed_by_name).
     */
    public function show(int $id): JsonResponse
    {
        $item = MercadoPagoRejectedValidation::with(['user:id,name,nro_doc,email,identity_validated', 'approvedBy:id,name', 'reviewedBy:id,name'])
            ->findOrFail($id);

        return response()->json([
            'data' => [
                'id' => $item->id,
                'user_id' => $item->user_id,
                'user_name' => $item->user ? $item->user->name : null,
                'user_nro_doc' => $item->user ? $item->user->nro_doc : null,
                'user_email' => $item->user ? $item->user->email : null,
                'user_identity_validated' => $item->user ? (bool) $item->user->identity_validated : false,
                'reject_reason' => $item->reject_reason,
                'created_at' => $item->created_at->toDateTimeString(),
                'mp_payload' => $item->mp_payload,
                'approved_at' => $item->approved_at ? $item->approved_at->toDateTimeString() : null,
                'approved_by' => $item->approved_by,
                'approved_by_name' => $item->approvedBy ? $item->approvedBy->name : null,
                'review_status' => $item->review_status,
                'review_note' => $item->review_note,
                'reviewed_at' => $item->reviewed_at ? $item->reviewed_at->toDateTimeString() : null,
                'reviewed_by' => $item->reviewed_by,
                'reviewed_by_name' => $item->reviewedBy ? $item->reviewedBy->name : null,
            ],
        ]);
    }

    /**
     * POST /api/admin/mercado-pago-rejected-validations/{id}/review - action: approve|reject|pending, note: string (required for reject/pending, optional for approve).
     * Records review_status, review_note, reviewed_at, reviewed_by. Approve: sets user identity_validated; Reject/Pending: clears user identity validation.
     */
    public function review(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'action' => 'required|in:approve,reject,pending',
            'note' => 'required_if:action,reject,pending|nullable|string|min:1',
        ]);

        $item = MercadoPagoRejectedValidation::with('user')->findOrFail($id);
        $user = $item->user;
        if (!$user) {
            return response()->json(['error' => 'User not found.'], 404);
        }

        $admin = $request->user();
        $item->review_status = $validated['action'] === 'approve' ? 'approved' : ($validated['action'] === 'reject' ? 'rejected' : 'pending');
        $item->review_note = $validated['note'] ?? '';
        $item->reviewed_at = now();
        $item->reviewed_by = $admin->id;

        if ($validated['action'] === 'approve') {
            $user->identity_validated = true;
            $user->identity_validated_at = now();
            $user->identity_validation_type = 'manual';
            $user->identity_validation_rejected_at = null;
            $user->identity_validation_reject_reason = null;
            $user->save();
            $item->approved_at = now();
            $item->approved_by = $admin->id;
        } else {
            $user->identity_validated = false;
            $user->identity_validated_at = null;
            $user->identity_validation_type = null;
            $user->identity_validation_rejected_at = null;
            $user->identity_validation_reject_reason = null;
            $user->save();
        }

        $item->save();
        $item->load(['user:id,name,nro_doc,email,identity_validated', 'approvedBy:id,name', 'reviewedBy:id,name']);

        $data = [
            'id' => $item->id,
            'user_id' => $item->user_id,
            'user_name' => $item->user ? $item->user->name : null,
            'user_nro_doc' => $item->user ? $item->user->nro_doc : null,
            'user_email' => $item->user ? $item->user->email : null,
            'user_identity_validated' => $item->user ? (bool) $item->user->identity_validated : false,
            'reject_reason' => $item->reject_reason,
            'created_at' => $item->created_at->toDateTimeString(),
            'mp_payload' => $item->mp_payload,
            'approved_at' => $item->approved_at ? $item->approved_at->toDateTimeString() : null,
            'approved_by' => $item->approved_by,
            'approved_by_name' => $item->approvedBy ? $item->approvedBy->name : null,
            'review_status' => $item->review_status,
            'review_note' => $item->review_note,
            'reviewed_at' => $item->reviewed_at ? $item->reviewed_at->toDateTimeString() : null,
            'reviewed_by' => $item->reviewed_by,
            'reviewed_by_name' => $item->reviewedBy ? $item->reviewedBy->name : null,
        ];

        return response()->json(['data' => $data]);
    }

    /**
     * POST /api/admin/mercado-pago-rejected-validations/{id}/approve - validate the user manually (legacy shortcut; use review with action=approve).
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $request->merge(['action' => 'approve', 'note' => '']);
        return $this->review($request, $id);
    }
}
