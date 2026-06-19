<?php

namespace STS\Http\Controllers\Api\v1;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use STS\Http\Controllers\Controller;
use STS\Http\ExceptionWithErrors;
use STS\Models\ManualIdentityValidation;
use STS\Services\HeicToJpegConverter;
use STS\Services\ImageUploadValidator;
use STS\Services\ManualIdentityValidationResubmitPolicy;
use STS\Services\MercadoPagoService;
use STS\Support\ImageAttachmentRules;

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
        if (! config('carpoolear.identity_validation_enabled', false)) {
            return response()->json(['cost_cents' => 0]);
        }
        if (! config('carpoolear.identity_validation_manual_enabled', false)) {
            return response()->json(['cost_cents' => 0]);
        }
        $costCents = config('carpoolear.manual_identity_validation_cost_cents', 0);

        return response()->json(['cost_cents' => $costCents]);
    }

    /**
     * GET /api/users/manual-identity-validation - current user's latest submission status
     */
    public function status(ManualIdentityValidationResubmitPolicy $resubmitPolicy)
    {
        if (! config('carpoolear.identity_validation_enabled', false)) {
            return response()->json($resubmitPolicy->statusPayload(null));
        }
        $user = auth()->user();
        $latest = ManualIdentityValidation::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->first();

        return response()->json($resubmitPolicy->statusPayload($latest));
    }

    /**
     * POST /api/users/manual-identity-validation/preference - create MP payment preference and request record
     */
    public function createPreference(Request $request, MercadoPagoService $mpService, ManualIdentityValidationResubmitPolicy $resubmitPolicy)
    {
        $user = auth()->user();
        if (! config('carpoolear.identity_validation_enabled', false)) {
            throw new ExceptionWithErrors('Identity validation is not available.', [], 503);
        }
        if (! config('carpoolear.identity_validation_manual_enabled', false)) {
            throw new ExceptionWithErrors('Manual identity validation is not available.', [], 503);
        }
        $costCents = config('carpoolear.manual_identity_validation_cost_cents', 0);
        if ($costCents <= 0) {
            throw new ExceptionWithErrors('Manual identity validation is not available.', []);
        }

        $latest = ManualIdentityValidation::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->first();
        if ($latest && $resubmitPolicy->canResubmitWithoutPayment($latest)) {
            throw new ExceptionWithErrors('You can resubmit your documents without paying again.', []);
        }

        // Reuse existing unpaid request so user can complete payment
        $validationRequest = ManualIdentityValidation::where('user_id', $user->id)
            ->where('paid', false)
            ->orderBy('created_at', 'desc')
            ->first();

        if (! $validationRequest) {
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
        if (! $initPoint) {
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
     * POST /api/users/manual-identity-validation/qr-order - create MP QR order for manual validation payment
     * Returns request_id, qr_data (string to render as QR), order_id. Frontend displays QR; user scans with MP app.
     * Payment confirmation is via webhook; frontend can poll status until paid.
     */
    public function createQrOrder(Request $request, MercadoPagoService $mpService, ManualIdentityValidationResubmitPolicy $resubmitPolicy)
    {
        $user = auth()->user();
        if (! config('carpoolear.identity_validation_enabled', false)) {
            throw new ExceptionWithErrors('Identity validation is not available.', [], 503);
        }
        if (! config('carpoolear.identity_validation_manual_enabled', false)) {
            throw new ExceptionWithErrors('Manual identity validation is not available.', [], 503);
        }
        if (! config('carpoolear.identity_validation_manual_qr_enabled', false)) {
            throw new ExceptionWithErrors('QR payment is not available.', [], 503);
        }
        $posExternalId = config('carpoolear.qr_payment_pos_external_id', '');
        if ($posExternalId === '' || $posExternalId === null) {
            throw new ExceptionWithErrors('QR payment is not available.', [], 503);
        }
        $costCents = config('carpoolear.manual_identity_validation_cost_cents', 0);
        if ($costCents <= 0) {
            throw new ExceptionWithErrors('Manual identity validation is not available.', []);
        }

        $latest = ManualIdentityValidation::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->first();
        if ($latest && $resubmitPolicy->canResubmitWithoutPayment($latest)) {
            throw new ExceptionWithErrors('You can resubmit your documents without paying again.', []);
        }

        $validationRequest = ManualIdentityValidation::where('user_id', $user->id)
            ->where('paid', false)
            ->orderBy('created_at', 'desc')
            ->first();

        if (! $validationRequest) {
            $validationRequest = ManualIdentityValidation::create([
                'user_id' => $user->id,
                'paid' => false,
                'review_status' => ManualIdentityValidation::REVIEW_STATUS_PENDING,
            ]);
        }

        try {
            $result = $mpService->createQrOrderForManualValidation($validationRequest->id, $costCents);
        } catch (\Exception $e) {
            if ($validationRequest->wasRecentlyCreated) {
                $validationRequest->delete();
            }
            throw $e;
        }

        if (empty($result['qr_data'])) {
            if ($validationRequest->wasRecentlyCreated) {
                $validationRequest->delete();
            }
            throw new ExceptionWithErrors('Failed to create QR order.', []);
        }

        return response()->json([
            'request_id' => $result['request_id'],
            'qr_data' => $result['qr_data'],
            'order_id' => $result['order_id'],
        ]);
    }

    /**
     * POST /api/users/manual-identity-validation - submit 3 images (request_id + front, back, selfie)
     */
    public function submit(Request $request, ManualIdentityValidationResubmitPolicy $resubmitPolicy)
    {
        $user = auth()->user();
        $requestId = $request->input('request_id');
        if (! $requestId) {
            throw new ExceptionWithErrors('request_id is required.', ['request_id' => ['required']]);
        }

        if (! config('carpoolear.identity_validation_enabled', false)) {
            throw new ExceptionWithErrors('Identity validation is not available.', [], 503);
        }
        if (! config('carpoolear.identity_validation_manual_enabled', false)) {
            throw new ExceptionWithErrors('Manual identity validation is not available.', [], 503);
        }

        $validationRequest = ManualIdentityValidation::where('id', $requestId)->where('user_id', $user->id)->first();
        if (! $validationRequest) {
            throw new ExceptionWithErrors('Invalid request.', [], 404);
        }

        if (! $validationRequest->paid) {
            throw new ExceptionWithErrors('Payment is required before submitting images.', [], 422);
        }

        $isResubmit = $validationRequest->review_status === ManualIdentityValidation::REVIEW_STATUS_REJECTED;
        if ($isResubmit) {
            if (! $resubmitPolicy->canResubmitWithoutPayment($validationRequest)) {
                throw new ExceptionWithErrors('Submission limit reached. Payment is required to try again.', [], 422);
            }
        } elseif ($validationRequest->submitted_at !== null) {
            throw new ExceptionWithErrors('Documents were already submitted for this request.', [], 422);
        }

        $front = $request->file('front_image');
        $back = $request->file('back_image');
        $selfie = $request->file('selfie_image');

        if (! $front || ! $back || ! $selfie) {
            throw new ExceptionWithErrors(
                'All three images are required: front_image, back_image, selfie_image.',
                []
            );
        }

        $laravelValidator = Validator::make(
            ['front_image' => $front, 'back_image' => $back, 'selfie_image' => $selfie],
            [
                'front_image' => ImageAttachmentRules::FILE,
                'back_image' => ImageAttachmentRules::FILE,
                'selfie_image' => ImageAttachmentRules::FILE,
            ],
        );
        if ($laravelValidator->fails()) {
            throw new ExceptionWithErrors('Invalid image upload.', $laravelValidator->errors()->toArray());
        }

        $validator = app(ImageUploadValidator::class);
        foreach (['front_image' => $front, 'back_image' => $back, 'selfie_image' => $selfie] as $field => $file) {
            $result = $validator->validate(
                $file,
                $field,
                ImageAttachmentRules::ALLOWED_MIMES,
                ImageAttachmentRules::ALLOWED_EXTENSIONS,
            );
            if (! ($result['valid'] ?? true)) {
                throw new ExceptionWithErrors('Invalid image upload.', $result['errors'] ?? []);
            }
        }

        $basePath = 'identity_validations/'.$validationRequest->id;
        $converter = app(HeicToJpegConverter::class);

        $frontPath = $this->storeIdentityImage($front, $basePath, $converter);
        $backPath = $this->storeIdentityImage($back, $basePath, $converter);
        $selfiePath = $this->storeIdentityImage($selfie, $basePath, $converter);

        $validationRequest->front_image_path = $frontPath;
        $validationRequest->back_image_path = $backPath;
        $validationRequest->selfie_image_path = $selfiePath;
        $validationRequest->submitted_at = now();
        $validationRequest->images_purged_at = null;
        $validationRequest->submission_count = (int) $validationRequest->submission_count + 1;
        $validationRequest->review_status = ManualIdentityValidation::REVIEW_STATUS_PENDING;
        $validationRequest->reviewed_by = null;
        $validationRequest->reviewed_at = null;
        $validationRequest->review_note = '';
        $validationRequest->save();

        return response()->json([
            'message' => 'Submission received.',
            'request_id' => $validationRequest->id,
        ], 201);
    }

    /**
     * Store an identity validation image, converting HEIC/HEIF to JPEG when configured.
     */
    private function storeIdentityImage($file, string $basePath, HeicToJpegConverter $converter): string
    {
        $jpegContent = $converter->convert($file);
        if ($jpegContent !== null) {
            $path = $basePath.'/'.Str::random(40).'.jpg';
            Storage::disk('local')->put($path, $jpegContent);

            return $path;
        }

        return $file->store($basePath, 'local');
    }
}
