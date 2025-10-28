<?php

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Phone Verification Rate Limiters
 * 
 * To disable rate limiting for testing, set the following environment variable:
 * DISABLE_PHONE_VERIFICATION_RATE_LIMIT=true
 */

// Phone verification rate limiters
RateLimiter::for('phone-verification-send', function (Request $request) {
    // Check if rate limiting is disabled for testing
    if (env('DISABLE_PHONE_VERIFICATION_RATE_LIMIT', false)) {
        \Log::info('Phone verification rate limiting disabled for testing');
        return []; // No limits
    }
    
    $user = $request->user();
    $ip = $request->ip();
    
    $limits = [];
    
    // Always limit by IP (prevents abuse from single IP creating multiple accounts)
    $limits[] = Limit::perHour(5)->by($ip . ':send'); // 5 send requests per hour per IP
    \Log::info('IP: ' . $ip);
    \Log::info('limits: ' . json_encode($limits));
    
    // If user is authenticated, also limit by user ID
    if ($user) {
        $limits[] = Limit::perHour(3)->by($user->id . ':send'); // 3 send requests per hour per user
    }
    
    return $limits;
});

RateLimiter::for('phone-verification-verify', function (Request $request) {
    // Check if rate limiting is disabled for testing
    if (env('DISABLE_PHONE_VERIFICATION_RATE_LIMIT', false)) {
        \Log::info('Phone verification rate limiting disabled for testing');
        return []; // No limits
    }
    
    $user = $request->user();
    $ip = $request->ip();
    
    $limits = [];
    
    // Always limit by IP (prevents brute force from single IP)
    $limits[] = Limit::perHour(20)->by($ip . ':verify'); // 20 verify attempts per hour per IP
    
    // If user is authenticated, also limit by user ID
    if ($user) {
        $limits[] = Limit::perHour(10)->by($user->id . ':verify'); // 10 verify attempts per hour per user
    }
    
    return $limits;
});

RateLimiter::for('phone-verification-resend', function (Request $request) {
    // Check if rate limiting is disabled for testing
    if (env('DISABLE_PHONE_VERIFICATION_RATE_LIMIT', false)) {
        \Log::info('Phone verification rate limiting disabled for testing');
        return []; // No limits
    }
    
    $user = $request->user();
    $ip = $request->ip();
    
    $limits = [];
    
    // Always limit by IP
    $limits[] = Limit::perHour(3)->by($ip . ':resend'); // 3 resend requests per hour per IP
    
    // If user is authenticated, also limit by user ID
    if ($user) {
        $limits[] = Limit::perHour(2)->by($user->id . ':resend'); // 2 resend requests per hour per user
    }
    
    return $limits;
});

RateLimiter::for('phone-verification-status', function (Request $request) {
    // Check if rate limiting is disabled for testing
    if (env('DISABLE_PHONE_VERIFICATION_RATE_LIMIT', false)) {
        \Log::info('Phone verification rate limiting disabled for testing');
        return []; // No limits
    }
    
    $user = $request->user();
    $ip = $request->ip();
    
    $limits = [];
    
    // Always limit by IP
    $limits[] = Limit::perHour(60)->by($ip . ':status'); // 60 status checks per hour per IP
    
    // If user is authenticated, also limit by user ID
    if ($user) {
        $limits[] = Limit::perHour(30)->by($user->id . ':status'); // 30 status checks per hour per user
    }
    
    return $limits;
});

/**
 * Password Reset Rate Limiters
 * 
 * To disable rate limiting for testing, set the following environment variable:
 * DISABLE_PASSWORD_RESET_RATE_LIMIT=true
 */

// Password reset rate limiters
RateLimiter::for('password-reset', function (Request $request) {
    $ip = $request->ip();
    $email = $request->get('email');
    
    // Check if rate limiting is disabled for testing/debugging
    if (config('carpoolear.disable_password_reset_rate_limit', false)) {
        \Log::warning('Password reset rate limiting DISABLED', [
            'email' => $email,
            'ip' => $ip,
            'timestamp' => now()->toIso8601String(),
            'reason' => 'carpoolear.disable_password_reset_rate_limit=true'
        ]);
        
        // Also log to email_logs if enabled
        if (config('mail.log_emails', false)) {
            \Log::channel('email_logs')->warning('PASSWORD_RESET_RATE_LIMITING_DISABLED', [
                'email' => $email,
                'ip' => $ip,
                'timestamp' => now()->toIso8601String()
            ]);
        }
        
        return []; // No limits
    }
    
    $limits = [];
    
    // Limit by IP to prevent abuse
    $limits[] = Limit::perHour(5)->by($ip . ':password-reset'); // 5 password reset requests per hour per IP
    
    // Limit by email to prevent spam to specific users
    if ($email) {
        $limits[] = Limit::perHour(3)->by($email . ':password-reset'); // 3 password reset requests per hour per email
        $limits[] = Limit::perDay(10)->by($email . ':password-reset-daily'); // 10 password reset requests per day per email
    }
    
    return $limits;
}); 