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
                    'created_at' => $item->created_at->toDateTimeString(),
                ];
            });

        return response()->json(['data' => $items]);
    }

    /**
     * GET /api/admin/mercado-pago-rejected-validations/{id} - single with user (id, name, nro_doc, email) and stored mp_payload (email, phone, address, first_name, last_name, country_id, identification, registration_date). Includes approved_at, approved_by, approved_by_name when validated by admin.
     */
    public function show(int $id): JsonResponse
    {
        $item = MercadoPagoRejectedValidation::with(['user:id,name,nro_doc,email,identity_validated', 'approvedBy:id,name'])
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
            ],
        ]);
    }

    /**
     * POST /api/admin/mercado-pago-rejected-validations/{id}/approve - validate the user manually (identity_validated=true, identity_validation_type=manual), record who approved.
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $item = MercadoPagoRejectedValidation::with('user')->findOrFail($id);

        if ($item->approved_at !== null) {
            return response()->json(['error' => 'This rejection was already approved.'], 422);
        }

        $user = $item->user;
        if (!$user) {
            return response()->json(['error' => 'User not found.'], 404);
        }

        $admin = $request->user();

        $user->identity_validated = true;
        $user->identity_validated_at = now();
        $user->identity_validation_type = 'manual';
        $user->identity_validation_rejected_at = null;
        $user->identity_validation_reject_reason = null;
        $user->save();

        $item->approved_at = now();
        $item->approved_by = $admin->id;
        $item->save();

        $item->load(['user:id,name,nro_doc,email,identity_validated', 'approvedBy:id,name']);

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
            ],
            'message' => 'User validated successfully.',
        ]);
    }
}
