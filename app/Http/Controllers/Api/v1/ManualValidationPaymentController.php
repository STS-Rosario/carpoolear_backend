<?php

namespace STS\Http\Controllers\Api\v1;

use STS\Http\Controllers\Controller;
use STS\Models\ManualIdentityValidation;
use Illuminate\Http\Request;

class ManualValidationPaymentController extends Controller
{
    /**
     * GET /api/mercadopago/manual-validation-success - MP redirects here after payment.
     * Set paid=true, store MP payment_id for tracking, and redirect to frontend upload page.
     */
    public function success(Request $request)
    {
        $requestId = $request->query('request_id');
        $result = $request->query('result', 'success');
        // MP may send payment_id or collection_id in the redirect URL
        $paymentId = $request->query('payment_id') ?: $request->query('collection_id');

        $frontendBase = rtrim(config('services.mercadopago.oauth_frontend_redirect', config('app.url')), '/');
        $redirectUrl = $frontendBase . '/setting/identity-validation/manual';

        if ($requestId) {
            $validationRequest = ManualIdentityValidation::find($requestId);
            if ($validationRequest && $result === 'success') {
                $validationRequest->paid = true;
                $validationRequest->paid_at = now();
                if ($paymentId !== null && $paymentId !== '') {
                    $validationRequest->payment_id = (string) $paymentId;
                }
                $validationRequest->save();
                \Log::info('Manual identity validation payment success', [
                    'request_id' => $requestId,
                    'user_id' => $validationRequest->user_id,
                    'payment_id' => $paymentId ?? $validationRequest->payment_id,
                ]);
            }
            $redirectUrl .= '?request_id=' . $requestId;
            if ($result !== 'success') {
                $redirectUrl .= '&payment_result=' . urlencode($result);
            } else {
                $redirectUrl .= '&payment_success=1';
            }
        }

        return redirect($redirectUrl);
    }
}
