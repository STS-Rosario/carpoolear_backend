# Password Reset Configuration Reference

All password reset and email logging settings are centralized in `config/carpoolear.php`.

## 📍 Configuration Location

**File:** `config/carpoolear.php`

```php
return [
    // ... other carpoolear settings ...

    // Password reset rate limiting
    'disable_password_reset_rate_limit' => env('DISABLE_PASSWORD_RESET_RATE_LIMIT', false),

    // Email logging
    'log_emails' => env('LOG_EMAILS', false),
    'email_log_daily_days' => env('EMAIL_LOG_DAILY_DAYS', 30),
];
```

## 🎛️ Environment Variables

Add to your `.env` file:

```env
# Password Reset Rate Limiting
# Set to true to disable rate limiting for debugging
DISABLE_PASSWORD_RESET_RATE_LIMIT=false

# Email Logging
# Set to true to enable detailed email operation logging
LOG_EMAILS=false
EMAIL_LOG_DAILY_DAYS=30
```

## 🔧 Configuration Options

### `disable_password_reset_rate_limit`

**Config Key:** `carpoolear.disable_password_reset_rate_limit`  
**Env Variable:** `DISABLE_PASSWORD_RESET_RATE_LIMIT`  
**Default:** `false`

**When `false` (Production):**
- ✅ Rate limiting is ACTIVE
- ✅ 5 requests per hour per IP
- ✅ 3 requests per hour per email
- ✅ 10 requests per day per email
- ✅ Protection against abuse

**When `true` (Debugging):**
- ⚠️ Rate limiting is DISABLED
- ⚠️ All requests are logged with WARNING level
- ⚠️ Use ONLY for debugging to identify culprit emails
- ⚠️ Never leave enabled in production

**Access in Code:**
```php
if (config('carpoolear.disable_password_reset_rate_limit')) {
    // Rate limiting is disabled
}
```

---

### `log_emails`

**Config Key:** `carpoolear.log_emails`  
**Env Variable:** `LOG_EMAILS`  
**Default:** `false`

**When `false` (Production):**
- Basic logs to `storage/logs/laravel.log`
- Minimal log verbosity
- Lower disk usage

**When `true` (Debugging):**
- ✅ Detailed logs to `storage/logs/email.log`
- ✅ Structured JSON data for analysis
- ✅ All password reset events logged
- ✅ Can be enabled independently of rate limiting

**Access in Code:**
```php
if (config('carpoolear.log_emails')) {
    \Log::channel('email_logs')->info('EVENT_NAME', $data);
}
```

---

### `email_log_daily_days`

**Config Key:** `carpoolear.email_log_daily_days`  
**Env Variable:** `EMAIL_LOG_DAILY_DAYS`  
**Default:** `30`

**Description:**
- Number of days to keep email log files
- Logs are automatically rotated daily
- Old logs are deleted after this many days
- Only applies when `log_emails` is `true`

**Access in Code:**
```php
$days = config('carpoolear.email_log_daily_days');
```

---

## 📊 Configuration Scenarios

### Scenario 1: Production (Normal Operation)

```env
DISABLE_PASSWORD_RESET_RATE_LIMIT=false
LOG_EMAILS=false
```

**Result:**
- Full rate limiting protection
- Minimal logging
- Optimal performance

---

### Scenario 2: Production (Active Issue Debugging)

```env
DISABLE_PASSWORD_RESET_RATE_LIMIT=false
LOG_EMAILS=true
EMAIL_LOG_DAILY_DAYS=7
```

**Result:**
- Full rate limiting protection (stays enabled)
- Detailed logging for 7 days
- Can diagnose issues without exposing to abuse

---

### Scenario 3: Finding Culprit Email

```env
DISABLE_PASSWORD_RESET_RATE_LIMIT=true  # ⚠️ Temporary only!
LOG_EMAILS=true
EMAIL_LOG_DAILY_DAYS=30
```

**Result:**
- ⚠️ NO rate limiting (allows all requests through)
- ✅ Detailed logging of ALL requests
- ✅ Can identify which email is being abused
- ⚠️ Must re-enable protection after debugging!

**Remember to disable after debugging:**
```bash
# Re-enable protection
sed -i '' 's/DISABLE_PASSWORD_RESET_RATE_LIMIT=true/DISABLE_PASSWORD_RESET_RATE_LIMIT=false/' .env
sed -i '' 's/LOG_EMAILS=true/LOG_EMAILS=false/' .env
php artisan config:clear
```

---

### Scenario 4: Development/Testing

```env
DISABLE_PASSWORD_RESET_RATE_LIMIT=true
LOG_EMAILS=true
EMAIL_LOG_DAILY_DAYS=7
```

**Result:**
- No restrictions for easy testing
- Full logging for debugging
- Shorter retention to save disk space

---

## 🔐 Security Best Practices

### ✅ DO

1. **Default to false** - Keep both disabled by default in `.env.example`
2. **Document changes** - Add comments when temporarily enabling
3. **Set reminders** - Use calendar alerts to re-check settings
4. **Monitor logs** - Check log file sizes regularly
5. **Clear cache** - Always run `php artisan config:clear` after changes

### ❌ DON'T

1. **Leave debugging enabled** - Always re-enable protection after debugging
2. **Commit `.env`** - Never commit `.env` with debugging enabled
3. **Ignore disk space** - Email logs can grow quickly
4. **Expose logs** - Logs contain PII (user emails, IPs)
5. **Skip cache clear** - Config changes won't take effect without it

---

## 🧪 Testing Your Configuration

### Verify Rate Limiting is Active

```bash
# Run 4 times quickly
for i in {1..4}; do
  curl -X POST http://your-app.test/api/reset-password \
    -H "Content-Type: application/json" \
    -d '{"email":"test@example.com"}'
  echo ""
done

# Expected: First 3 succeed, 4th returns 429
```

### Verify Email Logging is Working

```bash
# Make a request
curl -X POST http://your-app.test/api/reset-password \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com"}'

# Check log
tail -5 storage/logs/email.log

# Should see: PASSWORD_RESET_REQUEST, PASSWORD_RESET_QUEUED, etc.
```

### Verify Rate Limiting is Disabled

```bash
# Check config value
php artisan tinker
>>> config('carpoolear.disable_password_reset_rate_limit');
=> true  // If disabled

# Check logs for warning
tail storage/logs/laravel.log | grep "rate limiting DISABLED"
```

---

## 📱 Quick Reference Commands

```bash
# Enable debugging mode
echo "LOG_EMAILS=true" >> .env
echo "DISABLE_PASSWORD_RESET_RATE_LIMIT=true" >> .env
php artisan config:clear

# Disable debugging mode  
sed -i '' 's/LOG_EMAILS=true/LOG_EMAILS=false/' .env
sed -i '' 's/DISABLE_PASSWORD_RESET_RATE_LIMIT=true/DISABLE_PASSWORD_RESET_RATE_LIMIT=false/' .env
php artisan config:clear

# Check current settings
php artisan tinker
>>> config('carpoolear.log_emails');
>>> config('carpoolear.disable_password_reset_rate_limit');

# Clear rate limiting cache
php artisan cache:clear

# View email logs
tail -f storage/logs/email.log

# Find top offenders
grep "PASSWORD_RESET_REQUEST" storage/logs/email.log | \
  grep -o '"email":"[^"]*"' | \
  sort | uniq -c | sort -rn | head -10
```

---

## 🎯 Summary

All password reset configuration is now centralized in **`config/carpoolear.php`**:

- ✅ Single source of truth
- ✅ Consistent with other carpoolear settings
- ✅ Easy to manage and understand
- ✅ Independent control of logging and rate limiting

Access anywhere in your code:
```php
config('carpoolear.disable_password_reset_rate_limit')
config('carpoolear.log_emails')
config('carpoolear.email_log_daily_days')
```

