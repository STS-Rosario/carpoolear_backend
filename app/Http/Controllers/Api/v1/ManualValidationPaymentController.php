<?php

namespace STS\Http\Controllers\Api\v1;

use Illuminate\Http\Request;
use STS\Http\Controllers\Controller;
use STS\Models\ManualIdentityValidation;
use STS\Services\IdentityVerificationOutcome;

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
        $redirectUrl = $frontendBase.'/setting/identity-validation/manual';

        if ($requestId) {
            $validationRequest = ManualIdentityValidation::find($requestId);
            if ($validationRequest && $result === 'success') {
                $alreadyPaid = (bool) $validationRequest->paid;
                $validationRequest->markPaidAndAwaitingPhotosIfNeeded();
                if ($paymentId !== null && $paymentId !== '') {
                    $validationRequest->payment_id = (string) $paymentId;
                }
                $validationRequest->save();
                if (! $alreadyPaid) {
                    $this->emitManualPaymentEvent(
                        $validationRequest,
                        IdentityVerificationOutcome::NAME_PAYMENT_SUCCEEDED
                    );
                }
            } elseif ($validationRequest && $result !== 'success') {
                $this->emitManualPaymentEvent(
                    $validationRequest,
                    IdentityVerificationOutcome::NAME_PAYMENT_FAILED,
                    ['payment_result' => $result]
                );
            }
            $redirectUrl .= '?request_id='.$requestId;
            if ($result !== 'success') {
                $redirectUrl .= '&payment_result='.urlencode($result);
            } else {
                $redirectUrl .= '&payment_success=1';
            }
        }

        return redirect($redirectUrl);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function emitManualPaymentEvent(
        ManualIdentityValidation $validationRequest,
        string $name,
        array $metadata = []
    ): void {
        app(IdentityVerificationOutcome::class)->emit([
            'user_id' => $validationRequest->user_id,
            'method' => IdentityVerificationOutcome::METHOD_MANUAL,
            'name' => $name,
            'related_type' => 'manual_identity_validations',
            'related_id' => $validationRequest->id,
            'metadata' => $metadata,
        ]);
    }
}
