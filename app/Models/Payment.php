<?php

namespace STS\Models;

/**
 * Legacy alias for {@see PaymentAttempt} (`payment_attempts` table).
 * User and Trip still declare `hasMany(Payment::class, …)` from the pre-rename schema.
 */
class Payment extends PaymentAttempt {}
