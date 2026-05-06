<?php

namespace STS\Http\Controllers\Api\v1;

use Illuminate\Http\Request;
use STS\Http\Controllers\Controller;
use STS\Http\ExceptionWithErrors;
use STS\Services\Logic\PhoneVerificationManager;

class PhoneVerificationController extends Controller
{
    protected $phoneVerificationManager;

    public function __construct(PhoneVerificationManager $phoneVerificationManager)
    {
        $this->phoneVerificationManager = $phoneVerificationManager;
        $this->middleware('logged')->only(['send', 'verify', 'resend', 'status']);
    }

    /**
     * Send verification code to phone number (for logged in users)
     */
    public function send(Request $request)
    {
        $user = auth()->user();

        \Log::info('Phone verification send request', [
            'user_id' => $user->id,
            'phone' => $request->input('phone'),
            'ip' => $request->ip(),
        ]);

        $result = $this->phoneVerificationManager->sendVerificationCode($user, $request);

        if ($result === null) {
            $errors = $this->phoneVerificationManager->getErrors();
            \Log::error('Phone verification send failed', [
                'user_id' => $user->id,
                'phone' => $request->input('phone'),
                'errors' => $errors,
            ]);
            throw new ExceptionWithErrors('Validation failed', $errors);
        }

        \Log::info('Phone verification send successful', [
            'user_id' => $user->id,
            'phone' => $result['phone'],
        ]);

        return response()->json([
            'message' => 'Verification code sent successfully',
            'phone' => $result['phone'],
            'expires_in_minutes' => $result['expires_in_minutes'],
        ]);
    }

    /**
     * Verify phone number with code (for logged in users)
     */
    public function verify(Request $request)
    {
        $user = auth()->user();
        $result = $this->phoneVerificationManager->verifyPhoneNumber($user, $request);

        if ($result === null) {
            $errors = $this->phoneVerificationManager->getErrors();
            throw new ExceptionWithErrors('Validation failed', $errors);
        }

        return response()->json([
            'message' => 'Phone number verified successfully',
            'phone_verified' => $result['phone_verified'],
            'phone_verified_at' => $result['phone_verified_at'],
            'phone' => $result['phone'],
        ]);
    }

    /**
     * Resend verification code (for logged in users)
     */
    public function resend(Request $request)
    {
        $user = auth()->user();
        $result = $this->phoneVerificationManager->resendVerificationCode($user);

        if ($result === null) {
            $errors = $this->phoneVerificationManager->getErrors();
            throw new ExceptionWithErrors('Validation failed', $errors);
        }

        return response()->json([
            'message' => 'Verification code resent successfully',
            'expires_in_minutes' => $result['expires_in_minutes'],
        ]);
    }

    /**
     * Check phone verification status
     */
    public function status(Request $request)
    {
        $user = auth()->user();
        $status = $this->phoneVerificationManager->getVerificationStatus($user);

        return response()->json($status);
    }
}
