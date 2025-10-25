# Password Reset Gmail Rate Limiting Error Solution

## Problem Description

The application was experiencing Gmail rate limiting errors (450) when sending password reset emails. The error message indicated:

```
Expected response code "250/251/252" but got code "450", with message "450-4.2.1 The user you are trying to contact is receiving mail at a rate that prevents additional messages from being delivered."
```

This happens when:
- Too many emails are sent to the same recipient in a short time
- Gmail's anti-spam protection is triggered
- The recipient's mailbox is receiving emails too frequently

## Root Causes Identified

1. **No rate limiting** on password reset requests
2. **No retry mechanism** for failed emails
3. **No queue system** for email sending (emails sent synchronously)
4. **No cooldown period** between password reset attempts

## Email Sending Method

**Important:** The solution uses the **exact same email sending approach** as before:
- Uses Laravel's `Mail` facade with SMTP transport
- Continues to use your existing Gmail SMTP configuration
- Same `Mail::to($email)->send()` method that was working before
- No changes to the underlying email transport mechanism

The only difference is that emails are now **queued** instead of sent synchronously, which:
- Prevents rate limiting by spreading out email sends
- Provides automatic retry on failure
- Improves API response time

## Solutions Implemented

### 1. Rate Limiting for Password Reset Endpoint

**Files Modified:**
- `routes/rate_limits.php` - Added password reset rate limiters
- `routes/api.php` - Applied throttle middleware to reset-password route
- `app/Http/Controllers/Api/v1/AuthController.php` - Enhanced error handling

**Rate Limits Applied:**
- 5 password reset requests per hour per IP
- 3 password reset requests per hour per email
- 10 password reset requests per day per email

### 2. Queue System for Email Sending

**Files Created/Modified:**
- `app/Jobs/SendPasswordResetEmail.php` - New queued job for email sending
- `app/Services/Logic/UsersManager.php` - Updated to use queued emails
- `config/queue.php` - Added dedicated emails queue

**Features:**
- Emails are queued instead of sent synchronously
- Automatic retry with exponential backoff (1 min, 5 min, 15 min)
- 3 maximum attempts before marking as failed
- 10-second delay before processing to prevent immediate retries

### 3. Cooldown Period Between Requests

**Files Modified:**
- `app/Services/Logic/UsersManager.php` - Added cooldown logic
- `app/Repository/UserRepository.php` - Added method to get last reset

**Cooldown Rules:**
- 5-minute cooldown between password reset requests for the same email
- Clear error messages indicating remaining wait time

### 4. Improved Error Handling

**Files Created/Modified:**
- `app/Console/Commands/ProcessEmailQueue.php` - Command to process email queue
- `app/Console/Commands/CleanupPasswordResetTokens.php` - Command to clean expired tokens
- `app/Console/Kernel.php` - Scheduled cleanup tasks

**Error Handling Features:**
- Specific error messages for different failure types
- Automatic cleanup of expired tokens
- Detailed logging for debugging

## Configuration

### Environment Variables

Add these to your `.env` file:

```env
# Queue Configuration
QUEUE_CONNECTION=database
DB_QUEUE_TABLE=jobs

# Rate Limiting (optional - set to true to disable for testing)
DISABLE_PASSWORD_RESET_RATE_LIMIT=false

# Email Logging (set to true to enable detailed email logging)
LOG_EMAILS=false
EMAIL_LOG_DAILY_DAYS=30
```

### Email Logging

The system includes comprehensive email logging that can be controlled via the `LOG_EMAILS` environment variable:

**When `LOG_EMAILS=true`:**
- All password reset email operations are logged to `storage/logs/email.log`
- Logs are rotated daily and kept for 30 days (configurable via `EMAIL_LOG_DAILY_DAYS`)
- Each email operation is logged with structured data including:
  - User ID and email
  - Timestamps
  - Request IP address
  - Token (partial, for security)
  - Success/failure status
  - Error messages and stack traces (if applicable)

**When `LOG_EMAILS=false`:**
- Only basic logs are written to the regular `storage/logs/laravel.log`
- Reduced log verbosity for production

**Log Events:**
- `PASSWORD_RESET_REQUEST` - Initial request received
- `PASSWORD_RESET_QUEUED` - Email job queued successfully
- `PASSWORD_RESET_COOLDOWN` - Request blocked due to cooldown
- `PASSWORD_RESET_USER_NOT_FOUND` - Request for non-existent user
- `PASSWORD_RESET_EMAIL_SENDING` - Email sending started
- `PASSWORD_RESET_EMAIL_SUCCESS` - Email sent successfully
- `PASSWORD_RESET_EMAIL_FAILED` - Email sending failed (with retry)
- `PASSWORD_RESET_PERMANENTLY_FAILED` - Email failed after all retries

### Queue Processing

To process the email queue, run:

```bash
# Process emails queue
php artisan queue:process-emails

# Or use the general queue worker
php artisan queue:work --queue=emails
```

### Scheduled Tasks

The following tasks are automatically scheduled:

- **Daily at 4:00 AM**: Clean up expired password reset tokens
- **Email queue processing**: Should be run continuously in production

## Testing the Solution

### 1. Test Rate Limiting

```bash
# Make multiple requests to test rate limiting
curl -X POST http://your-app.com/api/reset-password \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com"}'
```

### 2. Test Cooldown Period

1. Request password reset for an email
2. Immediately request another reset for the same email
3. Should receive cooldown error message

### 3. Test Queue Processing

```bash
# Check queue status
php artisan queue:work --queue=emails --once

# Check failed jobs
php artisan queue:failed
```

## Monitoring

### Log Files to Monitor

- `storage/logs/laravel.log` - General application logs
- `storage/logs/email.log` - Detailed email operation logs (when `LOG_EMAILS=true`)
- Queue job logs in the `jobs` table
- Failed job logs in the `failed_jobs` table

### Key Metrics to Track

- Number of password reset requests per hour/day
- Email delivery success rate
- Queue processing time
- Failed job count

### Viewing Email Logs

```bash
# View all email logs
tail -f storage/logs/email.log

# View only password reset emails
tail -f storage/logs/email.log | grep PASSWORD_RESET

# View only failures
tail -f storage/logs/email.log | grep FAILED

# View today's email logs
cat storage/logs/email-$(date +%Y-%m-%d).log

# Search for specific email
grep "user@example.com" storage/logs/email.log

# View structured log data (if using JSON formatting)
grep PASSWORD_RESET_EMAIL_SUCCESS storage/logs/email.log | jq '.'
```

### Example Log Entries

**Successful Password Reset:**
```
[2025-10-25 20:30:00] production.INFO: PASSWORD_RESET_REQUEST {"email":"user@example.com","timestamp":"2025-10-25T20:30:00+00:00","ip":"192.168.1.1"}
[2025-10-25 20:30:00] production.INFO: PASSWORD_RESET_QUEUED {"user_id":123,"email":"user@example.com","token":"abc123def4...","timestamp":"2025-10-25T20:30:00+00:00"}
[2025-10-25 20:30:10] production.INFO: PASSWORD_RESET_EMAIL_SENDING {"user_id":123,"email":"user@example.com","attempt":1,"timestamp":"2025-10-25T20:30:10+00:00"}
[2025-10-25 20:30:15] production.INFO: PASSWORD_RESET_EMAIL_SUCCESS {"user_id":123,"email":"user@example.com","timestamp":"2025-10-25T20:30:15+00:00"}
```

**Cooldown Triggered:**
```
[2025-10-25 20:31:00] production.WARNING: PASSWORD_RESET_COOLDOWN {"user_id":123,"email":"user@example.com","remaining_minutes":4,"last_reset_at":"2025-10-25T20:30:00+00:00","timestamp":"2025-10-25T20:31:00+00:00"}
```

**Email Failure with Retry:**
```
[2025-10-25 20:30:10] production.ERROR: PASSWORD_RESET_EMAIL_FAILED {"user_id":123,"email":"user@example.com","error":"Expected response code 250 but got code 450","error_code":450,"attempt":1,"timestamp":"2025-10-25T20:30:10+00:00"}
[2025-10-25 20:31:10] production.INFO: PASSWORD_RESET_EMAIL_SENDING {"user_id":123,"email":"user@example.com","attempt":2,"timestamp":"2025-10-25T20:31:10+00:00"}
[2025-10-25 20:31:15] production.INFO: PASSWORD_RESET_EMAIL_SUCCESS {"user_id":123,"email":"user@example.com","timestamp":"2025-10-25T20:31:15+00:00"}
```

## Production Deployment

### 1. Database Migration

Ensure the `jobs` and `failed_jobs` tables exist:

```bash
php artisan queue:table
php artisan migrate
```

### 2. Queue Worker Setup

Set up a supervisor configuration for the queue worker:

```ini
[program:carpoolear-queue-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/your/app/artisan queue:work --queue=emails --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/path/to/your/app/storage/logs/queue-worker.log
stopwaitsecs=3600
```

### 3. Monitoring Commands

```bash
# Check queue status
php artisan queue:monitor

# Restart queue workers
php artisan queue:restart

# Clean up old failed jobs
php artisan queue:flush
```

## Benefits of This Solution

1. **Prevents Gmail Rate Limiting**: Rate limiting and cooldown periods prevent abuse
2. **Reliable Email Delivery**: Queue system with retry logic ensures emails are eventually sent
3. **Better User Experience**: Clear error messages and reasonable limits
4. **Scalable**: Queue system can handle high volumes of email requests
5. **Maintainable**: Automatic cleanup and monitoring tools

## Troubleshooting

### Common Issues

1. **Queue not processing**: Ensure queue worker is running
2. **Emails still failing**: Check SMTP configuration and Gmail settings
3. **Rate limiting too strict**: Adjust limits in `routes/rate_limits.php`
4. **Cooldown too long**: Modify cooldown period in `UsersManager.php`

### Debug Commands

```bash
# Check queue status
php artisan queue:work --queue=emails --once --verbose

# View failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all
```

This solution provides a robust, scalable approach to handling password reset emails while preventing Gmail rate limiting issues.
