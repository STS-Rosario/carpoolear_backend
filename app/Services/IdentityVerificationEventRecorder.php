<?php

namespace STS\Services;

use Illuminate\Support\Facades\Log;
use STS\Models\IdentityVerificationEvent;
use Throwable;

class IdentityVerificationEventRecorder
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function record(array $attributes): ?IdentityVerificationEvent
    {
        try {
            if (! isset($attributes['created_at'])) {
                $attributes['created_at'] = now();
            }

            return IdentityVerificationEvent::query()->create($attributes);
        } catch (Throwable $e) {
            Log::error('Identity verification event insert failed', [
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
