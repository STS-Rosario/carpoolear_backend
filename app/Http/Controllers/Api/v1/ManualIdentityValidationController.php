<?php

namespace STS\Http\Controllers\Api\v1;

use STS\Http\Controllers\Controller;
use STS\Http\ExceptionWithErrors;
use STS\Models\ManualIdentityValidation;
use STS\Services\MercadoPagoService;
use Illuminate\Http\Request;

class ManualIdentityValidationController extends Controller
{
    public function __construct()
    {
        $this->middleware('logged');
    }

    /**
     * GET /api/users/manual-identity-validation-cost
     */
    public function cost()
    {
        if (!config('carpoolear.identity_validation_manual_enabled', false)) {
            return response()->json(['cost_cents' => 0]);
        }
        $costCents = config('carpoolear.manual_identity_validation_cost_cents', 0);
        return response()->json(['cost_cents' => $costCents]);
    }

    /**
     * GET /api/users/manual-identity-validation - current user's latest submission status
     */
    public function status()
    {
        $user = auth()->user();
        $latest = ManualIdentityValidation::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$latest) {
            return response()->json([
                'has_submission' => false,
                'request_id' => null,
                'paid' => null,
                'paid_at' => null,
                'review_status' => null,
                'submitted_at' => null,
                'review_note' => null,
            ]);
        }

        return response()->json([
            'has_submission' => true,
            'request_id' => $latest->id,
            'paid' => $latest->paid,
            'paid_at' => $latest->paid_at ? $latest->paid_at->toDateTimeString() : null,
            'review_status' => $latest->review_status,
            'submitted_at' => $latest->submitted_at ? $latest->submitted_at->toDateTimeString() : null,
            'review_note' => $latest->review_note,
        ]);
    }

    /**
     * POST /api/users/manual-identity-validation/preference - create MP payment preference and request record
     */
    public function createPreference(Request $request, MercadoPagoService $mpService)
    {
        $user = auth()->user();
        if (!config('carpoolear.identity_validation_manual_enabled', false)) {
            throw new ExceptionWithErrors('Manual identity validation is not available.', [], 503);
        }
        $costCents = config('carpoolear.manual_identity_validation_cost_cents', 0);
        if ($costCents <= 0) {
            throw new ExceptionWithErrors('Manual identity validation is not available.', []);
        }

        // Reuse existing unpaid request so user can complete payment
        $validationRequest = ManualIdentityValidation::where('user_id', $user->id)
            ->where('paid', false)
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$validationRequest) {
            $validationRequest = ManualIdentityValidation::create([
                'user_id' => $user->id,
                'paid' => false,
                'review_status' => ManualIdentityValidation::REVIEW_STATUS_PENDING,
            ]);
        }

        try {
            $preference = $mpService->createPaymentPreferenceForManualValidation($validationRequest->id, $costCents, null);
        } catch (\Exception $e) {
            if ($validationRequest->wasRecentlyCreated) {
                $validationRequest->delete();
            }
            throw $e;
        }

        $initPoint = $preference->init_point ?? $preference->sandbox_init_point ?? null;
        if (!$initPoint) {
            if ($validationRequest->wasRecentlyCreated) {
                $validationRequest->delete();
            }
            throw new ExceptionWithErrors('Failed to create payment preference.', []);
        }

        return response()->json([
            'init_point' => $initPoint,
            'request_id' => $validationRequest->id,
        ]);
    }

    /**
     * POST /api/users/manual-identity-validation - submit 3 images (request_id + front, back, selfie)
     */
    public function submit(Request $request)
    {
        $user = auth()->user();
        $requestId = $request->input('request_id');
        if (!$requestId) {
            throw new ExceptionWithErrors('request_id is required.', ['request_id' => ['required']]);
        }

        if (!config('carpoolear.identity_validation_manual_enabled', false)) {
            throw new ExceptionWithErrors('Manual identity validation is not available.', [], 503);
        }

        $validationRequest = ManualIdentityValidation::where('id', $requestId)->where('user_id', $user->id)->first();
        if (!$validationRequest) {
            throw new ExceptionWithErrors('Invalid request.', [], 404);
        }

        if (!$validationRequest->paid) {
            throw new ExceptionWithErrors('Payment is required before submitting images.', [], 422);
        }

        $front = $request->file('front_image');
        $back = $request->file('back_image');
        $selfie = $request->file('selfie_image');

        if (!$front || !$back || !$selfie) {
            throw new ExceptionWithErrors('All three images are required: front_image, back_image, selfie_image.', []);
        }

        $basePath = 'identity_validations/' . $validationRequest->id;

        $frontPath = $front->store($basePath, 'local');
        $backPath = $back->store($basePath, 'local');
        $selfiePath = $selfie->store($basePath, 'local');

        $validationRequest->front_image_path = $frontPath;
        $validationRequest->back_image_path = $backPath;
        $validationRequest->selfie_image_path = $selfiePath;
        $validationRequest->submitted_at = now();
        $validationRequest->save();

        return response()->json([
            'message' => 'Submission received.',
            'request_id' => $validationRequest->id,
        ], 201);
    }
}
