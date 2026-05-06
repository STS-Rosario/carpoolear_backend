<?php

namespace STS\Services\Logic;

use Illuminate\Http\Request;
use STS\Models\PhoneVerification;
use STS\Models\User;
use STS\Repository\PhoneVerificationRepository;
use STS\Services\SmsService;
use Validator;

class PhoneVerificationManager extends BaseManager
{
    protected $phoneVerificationRepository;

    protected $smsService;

    public function __construct(PhoneVerificationRepository $phoneVerificationRepository, SmsService $smsService)
    {
        $this->phoneVerificationRepository = $phoneVerificationRepository;
        $this->smsService = $smsService;
    }

    /**
     * Validate phone verification request data
     */
    public function validatorSend(array $data)
    {
        return Validator::make($data, [
            'phone' => 'required|string|max:20',
        ]);
    }

    /**
     * Validate verification code request data
     */
    public function validatorVerify(array $data)
    {
        return Validator::make($data, [
            'code' => 'required|string|size:6',
        ]);
    }

    /**
     * Send verification code to phone number
     */
    public function sendVerificationCode(User $user, Request $request)
    {
        $validator = $this->validatorSend($request->all());
        if ($validator->fails()) {
            $this->setErrors($validator->errors());

            return null;
        }

        $phone = $request->input('phone');
        $formattedPhone = $this->smsService->formatPhoneNumber($phone);

        // Check if phone is already verified by another user
        $existingVerification = $this->phoneVerificationRepository->isPhoneVerifiedByAnotherUser($formattedPhone, $user->id);
        if ($existingVerification) {
            $this->setErrors(['phone' => 'Phone number is already verified by another user']);

            return null;
        }

        // Check if user already has a verification in progress
        $existingVerification = $this->phoneVerificationRepository->getLatestUnverifiedByUser($user->id);

        if ($existingVerification) {
            // Check if blocked due to too many failed attempts
            if ($existingVerification->isBlocked()) {
                $this->setErrors(['verification' => 'Too many failed attempts. Please request a new code.']);

                return null;
            }

            // Check cooldown for resending
            if (! $existingVerification->canResend()) {
                $nextResendTime = $existingVerification->getNextResendTime();
                $waitMinutes = now()->diffInMinutes($nextResendTime);
                $this->setErrors(['verification' => "Please wait {$waitMinutes} minutes before requesting another code"]);

                return null;
            }

            // Update existing verification
            $verification = $existingVerification;
        } else {
            // Create new verification
            $verification = new PhoneVerification;
            $verification->user_id = $user->id;
            $verification->phone_number = $formattedPhone;
            $verification->ip_address = $request->ip(); // Store IP for audit trail
        }

        // Generate verification code
        $code = str_pad(random_int(0, 999999), 6, STR_PAD_LEFT, '0');

        // Update verification record
        $verification->verification_code = $code;
        $verification->code_sent_at = now();
        $this->phoneVerificationRepository->update($verification);

        // Increment resend count if this is a resend
        if ($existingVerification) {
            $verification->incrementResendCount();
        }

        // Send SMS
        $expiresInMinutes = config('sms.verification.expires_in_minutes', 5);
        $message = str_replace(
            ['{code}', '{expires}'],
            [$code, $expiresInMinutes],
            config('sms.templates.verification')
        );

        // Debug logging
        \Log::info('Attempting to send verification SMS', [
            'user_id' => $user->id,
            'phone' => $formattedPhone,
            'message' => $message,
            'expires_in_minutes' => $expiresInMinutes,
        ]);

        $sent = $this->smsService->send($formattedPhone, $message);

        if (! $sent) {
            \Log::error('Failed to send verification SMS', [
                'user_id' => $user->id,
                'phone' => $formattedPhone,
                'message' => $message,
            ]);
            $this->setErrors(['sms' => 'Failed to send verification code']);

            return null;
        }

        \Log::info('Verification SMS sent successfully', [
            'user_id' => $user->id,
            'phone' => $formattedPhone,
        ]);

        return [
            'verification' => $verification,
            'phone' => $phone,
            'expires_in_minutes' => $expiresInMinutes,
        ];
    }

    /**
     * Verify phone number with code
     */
    public function verifyPhoneNumber(User $user, Request $request)
    {
        $validator = $this->validatorVerify($request->all());
        if ($validator->fails()) {
            $this->setErrors($validator->errors());

            return null;
        }

        $code = $request->input('code');

        // Get the latest unverified verification for this user
        $verification = $this->phoneVerificationRepository->getLatestUnverifiedByUser($user->id);

        if (! $verification) {
            $this->setErrors(['verification' => 'No pending verification found']);

            return null;
        }

        // Check if blocked due to too many failed attempts
        if ($verification->isBlocked()) {
            $this->setErrors(['verification' => 'Too many failed attempts. Please request a new code.']);

            return null;
        }

        // Check if code is expired
        if ($verification->isExpired()) {
            $this->setErrors(['code' => 'Verification code has expired']);

            return null;
        }

        // Verify the code
        if (! $verification->verifyCode($code)) {
            // Increment failed attempts
            $isBlocked = $verification->incrementFailedAttempts();

            if ($isBlocked) {
                $this->setErrors(['code' => 'Too many failed attempts. Please request a new code.'], 429);

                return null;
            }

            $this->setErrors(['code' => 'Invalid verification code']);

            return null;
        }

        // Mark as verified
        $verification->markAsVerified();
        $verification->resetFailedAttempts();

        // Update user's phone number
        $user->update([
            'mobile_phone' => $verification->phone_number,
            'phone_verified' => true,
            'phone_verified_at' => now(),
        ]);

        return [
            'verification' => $verification,
            'phone_verified' => true,
            'phone_verified_at' => $verification->verified_at,
            'phone' => $verification->phone_number,
        ];
    }

    /**
     * Resend verification code
     */
    public function resendVerificationCode(User $user)
    {
        // Get the latest unverified verification for this user
        $verification = $this->phoneVerificationRepository->getLatestUnverifiedByUser($user->id);

        if (! $verification) {
            $this->setErrors(['verification' => 'No pending verification found']);

            return null;
        }

        // Check if blocked due to too many failed attempts
        if ($verification->isBlocked()) {
            $this->setErrors(['verification' => 'Too many failed attempts. Please request a new code.']);

            return null;
        }

        // Check cooldown for resending
        if (! $verification->canResend()) {
            $nextResendTime = $verification->getNextResendTime();
            $waitMinutes = now()->diffInMinutes($nextResendTime);
            $this->setErrors(['verification' => "Please wait {$waitMinutes} minutes before requesting another code"]);

            return null;
        }

        // Generate new verification code
        $code = str_pad(random_int(0, 999999), 6, STR_PAD_LEFT, '0');

        // Update verification record
        $verification->verification_code = $code;
        $verification->code_sent_at = now();
        $this->phoneVerificationRepository->update($verification);

        // Increment resend count
        $verification->incrementResendCount();

        // Send SMS
        $expiresInMinutes = config('sms.verification.expires_in_minutes', 5);
        $message = str_replace(
            ['{code}', '{expires}'],
            [$code, $expiresInMinutes],
            config('sms.templates.verification')
        );

        // Debug logging
        \Log::info('Attempting to resend verification SMS', [
            'user_id' => $user->id,
            'phone' => $verification->phone_number,
            'message' => $message,
            'expires_in_minutes' => $expiresInMinutes,
        ]);

        $sent = $this->smsService->send($verification->phone_number, $message);

        if (! $sent) {
            \Log::error('Failed to resend verification SMS', [
                'user_id' => $user->id,
                'phone' => $verification->phone_number,
                'message' => $message,
            ]);
            $this->setErrors(['sms' => 'Failed to send verification code']);

            return null;
        }

        \Log::info('Verification SMS resent successfully', [
            'user_id' => $user->id,
            'phone' => $verification->phone_number,
        ]);

        return [
            'verification' => $verification,
            'expires_in_minutes' => $expiresInMinutes,
        ];
    }

    /**
     * Get phone verification status for user
     */
    public function getVerificationStatus(User $user)
    {
        if (! $user->mobile_phone) {
            return [
                'has_phone' => false,
                'phone_verified' => false,
            ];
        }

        // Get the latest verification for this user
        $verification = $this->phoneVerificationRepository->getLatestByUser($user->id);

        $response = [
            'has_phone' => true,
            'phone_verified' => $user->phone_verified,
            'phone_verified_at' => $user->phone_verified_at,
            'phone' => $user->mobile_phone,
        ];

        // Add pending verification info if exists
        if ($verification && ! $verification->verified) {
            $response['pending_verification'] = [
                'phone' => $verification->phone_number,
                'expires_at' => $verification->code_sent_at->addMinutes(5),
                'can_resend' => $verification->canResend(),
                'next_resend_time' => $verification->getNextResendTime(),
                'failed_attempts' => $verification->failed_attempts,
                'is_blocked' => $verification->isBlocked(),
            ];
        }

        return $response;
    }

    /**
     * Get verification statistics for user
     */
    public function getVerificationStats(User $user)
    {
        return $this->phoneVerificationRepository->getVerificationStats($user->id);
    }
}
