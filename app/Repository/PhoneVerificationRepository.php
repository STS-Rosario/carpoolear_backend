<?php

namespace STS\Repository;

use STS\Models\PhoneVerification;

class PhoneVerificationRepository
{
    /**
     * Create a new phone verification record
     */
    public function create(PhoneVerification $phoneVerification)
    {
        return $phoneVerification->save();
    }

    /**
     * Update an existing phone verification record
     */
    public function update(PhoneVerification $phoneVerification)
    {
        return $phoneVerification->save();
    }

    /**
     * Find phone verification by ID
     */
    public function find($id)
    {
        return PhoneVerification::find($id);
    }

    /**
     * Get the latest unverified verification for a user
     */
    public function getLatestUnverifiedByUser($userId)
    {
        return PhoneVerification::where('user_id', $userId)
            ->where('verified', false)
            ->latest()
            ->first();
    }

    /**
     * Get the latest verification for a user (verified or unverified)
     */
    public function getLatestByUser($userId)
    {
        return PhoneVerification::where('user_id', $userId)
            ->latest()
            ->first();
    }

    /**
     * Check if phone number is already verified by another user
     */
    public function isPhoneVerifiedByAnotherUser($phoneNumber, $excludeUserId)
    {
        return PhoneVerification::where('phone_number', $phoneNumber)
            ->where('verified', true)
            ->where('user_id', '!=', $excludeUserId)
            ->first();
    }

    /**
     * Get all verification attempts for a user
     */
    public function getByUser($userId)
    {
        return PhoneVerification::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Delete a phone verification record
     */
    public function delete(PhoneVerification $phoneVerification)
    {
        return $phoneVerification->delete();
    }

    /**
     * Get verification statistics for a user
     */
    public function getVerificationStats($userId)
    {
        return PhoneVerification::where('user_id', $userId)
            ->selectRaw('
                COUNT(*) as total_attempts,
                SUM(CASE WHEN verified = 1 THEN 1 ELSE 0 END) as successful_verifications,
                SUM(CASE WHEN verified = 0 THEN 1 ELSE 0 END) as failed_attempts
            ')
            ->first();
    }
}
