# Email Logging Guide

This guide explains how to use the email logging feature for debugging password reset and other email operations.

## Quick Start

### Enable Email Logging

Add this to your `.env` file:

```env
LOG_EMAILS=true
EMAIL_LOG_DAILY_DAYS=30
```

### View Logs

```bash
# Real-time email log monitoring
tail -f storage/logs/email.log

# View specific date
cat storage/logs/email-2025-10-25.log
```

## Configuration

### Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `LOG_EMAILS` | `false` | Enable/disable detailed email logging |
| `EMAIL_LOG_DAILY_DAYS` | `30` | Number of days to keep email logs |

### Log Location

- **File:** `storage/logs/email.log` (current day)
- **Rotated:** `storage/logs/email-YYYY-MM-DD.log` (previous days)
- **Retention:** Automatically deleted after configured days

## Log Events

### Password Reset Events

| Event | Level | Description |
|-------|-------|-------------|
| `PASSWORD_RESET_REQUEST` | INFO | Initial password reset request received |
| `PASSWORD_RESET_QUEUED` | INFO | Email job successfully queued |
| `PASSWORD_RESET_COOLDOWN` | WARNING | Request blocked due to cooldown period |
| `PASSWORD_RESET_USER_NOT_FOUND` | WARNING | Request for non-existent email |
| `PASSWORD_RESET_EMAIL_SENDING` | INFO | Email sending started (includes attempt number) |
| `PASSWORD_RESET_EMAIL_SUCCESS` | INFO | Email sent successfully |
| `PASSWORD_RESET_EMAIL_FAILED` | ERROR | Email sending failed (will retry) |
| `PASSWORD_RESET_PERMANENTLY_FAILED` | CRITICAL | Email failed after all retry attempts |

## Log Structure

All email logs include structured data in JSON format:

```json
{
  "user_id": 123,
  "email": "user@example.com",
  "timestamp": "2025-10-25T20:30:00+00:00",
  "attempt": 1,
  "token": "abc123def4...",
  "error": "Error message (if failed)",
  "error_code": 450,
  "stack_trace": "Full stack trace (on failure)",
  "ip": "192.168.1.1"
}
```

## Common Use Cases

### 1. Debug Why Email Wasn't Sent

```bash
# Search for specific email
grep "user@example.com" storage/logs/email.log

# Check if request was received
grep "PASSWORD_RESET_REQUEST" storage/logs/email.log | grep "user@example.com"

# Check if it was queued
grep "PASSWORD_RESET_QUEUED" storage/logs/email.log | grep "user@example.com"

# Check for failures
grep "PASSWORD_RESET_EMAIL_FAILED" storage/logs/email.log | grep "user@example.com"
```

### 2. Monitor Email Sending Success Rate

```bash
# Count successful sends today
grep "PASSWORD_RESET_EMAIL_SUCCESS" storage/logs/email-$(date +%Y-%m-%d).log | wc -l

# Count failed sends today
grep "PASSWORD_RESET_EMAIL_FAILED" storage/logs/email-$(date +%Y-%m-%d).log | wc -l

# Count permanently failed sends
grep "PASSWORD_RESET_PERMANENTLY_FAILED" storage/logs/email-$(date +%Y-%m-%d).log | wc -l
```

### 3. Identify Rate Limiting Issues

```bash
# Find rate limiting errors (450 error code)
grep "error_code.*450" storage/logs/email.log

# Check cooldown blocks
grep "PASSWORD_RESET_COOLDOWN" storage/logs/email.log
```

### 4. Track Email for Specific User

```bash
# Get all events for a user ID
grep '"user_id":123' storage/logs/email.log

# Get complete timeline with timestamps
grep '"user_id":123' storage/logs/email.log | jq '.timestamp, .event'
```

### 5. Analyze Retry Patterns

```bash
# Find emails that required retries
grep "PASSWORD_RESET_EMAIL_FAILED" storage/logs/email.log | \
  grep -o '"email":"[^"]*"' | sort | uniq -c | sort -rn

# Check how many attempts before success
grep "attempt" storage/logs/email.log | grep -o '"attempt":[0-9]*' | sort | uniq -c
```

## Performance Considerations

### Storage Usage

Email logs can grow quickly. Monitor disk usage:

```bash
# Check log file sizes
du -h storage/logs/email*.log

# Check total email log size
du -sh storage/logs/email*.log
```

### When to Enable

**Enable in:**
- Development environments (always)
- Staging environments (always)
- Production environments (when debugging issues)

**Disable in:**
- Production environments (normal operation)
- High-volume production (to reduce disk I/O)

### Cleanup

Logs are automatically rotated and cleaned up based on `EMAIL_LOG_DAILY_DAYS`. To manually clean up:

```bash
# Delete logs older than 7 days
find storage/logs -name "email-*.log" -mtime +7 -delete

# Keep only today's log
rm storage/logs/email-*.log
```

## Integration with Other Tools

### Log Aggregation Services

If using a log aggregation service (e.g., Papertrail, Loggly, Elasticsearch):

1. Configure your service to monitor `storage/logs/email*.log`
2. Set up alerts for `PASSWORD_RESET_PERMANENTLY_FAILED` events
3. Create dashboards for email success rates

### Monitoring Alerts

Set up alerts for:

```bash
# Alert on high failure rate (>10% in last hour)
failures=$(grep "PASSWORD_RESET_EMAIL_FAILED" storage/logs/email.log | tail -n 100 | wc -l)
if [ $failures -gt 10 ]; then
  echo "High email failure rate detected!"
fi

# Alert on permanent failures
if grep -q "PASSWORD_RESET_PERMANENTLY_FAILED" storage/logs/email-$(date +%Y-%m-%d).log; then
  echo "Email permanently failed! Manual intervention required."
fi
```

## Troubleshooting

### No Logs Generated

1. Check `LOG_EMAILS` is set to `true` in `.env`
2. Verify `storage/logs` directory is writable
3. Check Laravel cache is cleared: `php artisan config:clear`

### Logs Not Rotating

1. Verify `EMAIL_LOG_DAILY_DAYS` is set
2. Check Laravel scheduler is running: `php artisan schedule:run`
3. Ensure cron job is configured

### Large Log Files

1. Reduce retention period: Set `EMAIL_LOG_DAILY_DAYS=7`
2. Disable email logging when not needed: `LOG_EMAILS=false`
3. Archive old logs to external storage

## Best Practices

1. **Enable logging during troubleshooting** - Turn on when investigating issues
2. **Monitor disk space** - Set up alerts for low disk space
3. **Regular log review** - Check logs weekly for patterns
4. **Set up alerts** - Configure alerts for critical failures
5. **Document findings** - Keep notes on common issues and solutions
6. **Rotate logs appropriately** - Balance between retention and storage

## Security Considerations

- Logs contain user emails (PII) - treat as sensitive data
- Partial tokens are logged (first 10 chars only) - full tokens are never logged
- IP addresses are logged - comply with privacy regulations
- Secure log file access - restrict permissions to authorized users only
- Consider log encryption - for highly sensitive environments

## Example Workflow

### Debugging a Reported Issue

1. **Enable logging:**
   ```bash
   echo "LOG_EMAILS=true" >> .env
   php artisan config:clear
   ```

2. **Reproduce the issue:**
   - Ask user to request password reset

3. **Check logs:**
   ```bash
   tail -f storage/logs/email.log | grep "user@example.com"
   ```

4. **Analyze the timeline:**
   - Was request received? Check for `PASSWORD_RESET_REQUEST`
   - Was it queued? Check for `PASSWORD_RESET_QUEUED`
   - Was email sent? Check for `PASSWORD_RESET_EMAIL_SUCCESS`
   - Any failures? Check for `PASSWORD_RESET_EMAIL_FAILED`

5. **Take action based on findings:**
   - Rate limiting: Wait or increase limits
   - Email failure: Check SMTP settings
   - Cooldown: Expected behavior, inform user
   - User not found: Verify email address

6. **Disable logging when done:**
   ```bash
   echo "LOG_EMAILS=false" >> .env
   php artisan config:clear
   ```

This guide should help you effectively use the email logging system for debugging and monitoring.
