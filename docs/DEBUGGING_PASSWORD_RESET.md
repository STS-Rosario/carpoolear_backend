# Quick Guide: Debugging Password Reset Issues

## 🔍 Finding the Culprit Email Account

When you suspect someone is abusing the password reset feature or causing Gmail rate limiting, use this debugging workflow:

---

## 📝 Quick Setup (2 minutes)

### 1. Enable Debugging Mode

Edit `.env`:
```bash
LOG_EMAILS=true
DISABLE_PASSWORD_RESET_RATE_LIMIT=true
```

Clear cache:
```bash
php artisan config:clear
```

### 2. Monitor in Real-Time

```bash
# Terminal 1: Watch all password reset activity
tail -f storage/logs/email.log | grep PASSWORD_RESET

# Terminal 2: Watch disabled rate limiting warnings
tail -f storage/logs/laravel.log | grep "rate limiting DISABLED"
```

### 3. Let it Run

Leave it running for 30-60 minutes (or however long you suspect the issue occurs).

### 4. Analyze the Data

```bash
# Find the top offenders (most requests per email)
grep "PASSWORD_RESET_REQUEST" storage/logs/email.log | \
  grep -o '"email":"[^"]*"' | \
  sort | uniq -c | sort -rn | head -20
```

**Example Output:**
```
  47 "email":"spammer@example.com"     ← CULPRIT! 47 requests
  12 "email":"legitimate@example.com"   
   8 "email":"normal@example.com"
   3 "email":"user@example.com"
```

### 5. Disable Debugging Mode

Edit `.env`:
```bash
LOG_EMAILS=false
DISABLE_PASSWORD_RESET_RATE_LIMIT=false
```

Clear cache:
```bash
php artisan config:clear
```

---

## 🎯 Configuration Matrix

Choose the right configuration for your needs:

| Scenario | `LOG_EMAILS` | `DISABLE_PASSWORD_RESET_RATE_LIMIT` | Use Case |
|----------|--------------|-------------------------------------|----------|
| **Production (Normal)** | `false` | `false` | Normal operation, full protection |
| **Production (Debugging)** | `true` | `false` | Debug with protection enabled |
| **Finding Culprit** | `true` | `true` | Identify who's abusing the system |
| **Development** | `true` | `true` | Local testing without restrictions |

---

## 📊 Log Analysis Commands

### Count Requests Per Email
```bash
grep "PASSWORD_RESET_REQUEST" storage/logs/email.log | \
  grep -o '"email":"[^"]*"' | \
  sort | uniq -c | sort -rn
```

### Count Requests Per IP
```bash
grep "PASSWORD_RESET_REQUEST" storage/logs/email.log | \
  grep -o '"ip":"[^"]*"' | \
  sort | uniq -c | sort -rn
```

### Find Failed Emails
```bash
grep "PASSWORD_RESET_EMAIL_FAILED" storage/logs/email.log | \
  grep -o '"email":"[^"]*"' | \
  sort | uniq -c | sort -rn
```

### Timeline for Specific Email
```bash
grep "suspicious@example.com" storage/logs/email.log | \
  grep -o '"timestamp":"[^"]*"' | \
  sort
```

### Check Rate Limiting Bypass Logs
```bash
grep "PASSWORD_RESET_RATE_LIMITING_DISABLED" storage/logs/email.log
```

---

## 🚨 Warning Signs

Look for these patterns in your logs:

### **1. Automated Attacks**
```bash
# Requests from same email every few seconds
grep "spammer@example.com" storage/logs/email.log | \
  grep "PASSWORD_RESET_REQUEST" | \
  head -10
```

If you see regular intervals (e.g., exactly every 5 seconds), it's likely automated.

### **2. Distributed Attack**
```bash
# Same email from multiple IPs
grep "victim@example.com" storage/logs/email.log | \
  grep -o '"ip":"[^"]*"' | \
  sort | uniq
```

Multiple different IPs = coordinated attack.

### **3. High Volume from Single IP**
```bash
# Count all requests from an IP
grep '"ip":"192.168.1.1"' storage/logs/email.log | \
  wc -l
```

---

## 🛡️ After Finding the Culprit

### Option 1: Block Specific Email (Laravel Level)

Create a middleware or add to your controller:

```php
// In AuthController.php reset() method
$blockedEmails = ['spammer@example.com', 'abuser@example.com'];

if (in_array($email, $blockedEmails)) {
    \Log::warning("Blocked password reset attempt for blacklisted email: {$email}");
    return response()->json(['data' => 'ok']); // Pretend it worked
}
```

### Option 2: Block IP Address (Server Level)

```bash
# In your firewall/nginx/apache
# Block specific IP
sudo iptables -A INPUT -s 192.168.1.1 -j DROP
```

### Option 3: Tighten Rate Limits

Edit `routes/rate_limits.php`:

```php
// Make limits more strict
$limits[] = Limit::perHour(2)->by($email . ':password-reset'); // Was 3
$limits[] = Limit::perDay(5)->by($email . ':password-reset-daily'); // Was 10
```

---

## ⚠️ Important Security Notes

### **NEVER Leave Debugging Enabled**

1. ✅ Enable debugging for short periods only
2. ✅ Always re-enable rate limiting after debugging
3. ✅ Set calendar reminder to check if still disabled
4. ✅ Monitor disk space (logs can grow quickly)

### **Protect Sensitive Data**

- Logs contain user emails (PII)
- Secure log file access
- Delete old debug logs after analysis
- Consider rotating logs more frequently

### **Production Checklist**

Before leaving debugging mode:

```bash
# 1. Verify settings
grep "DISABLE_PASSWORD_RESET_RATE_LIMIT" .env
grep "LOG_EMAILS" .env

# 2. Should show:
# DISABLE_PASSWORD_RESET_RATE_LIMIT=false
# LOG_EMAILS=false

# 3. Clear cache
php artisan config:clear

# 4. Test rate limiting works
curl -X POST http://your-app.com/api/reset-password \
  -H "Content-Type: application/json" \
  -d '{"email":"test@test.com"}' 
# (Run 4 times - 4th should fail with 429)

# 5. Archive debug logs
mv storage/logs/email-*.log storage/logs/archive/
```

---

## 📈 Example Real-World Scenario

### Problem
Production logs showing Gmail rate limiting errors for user `victim@example.com`

### Investigation
```bash
# 1. Enable debugging
echo "LOG_EMAILS=true" >> .env
echo "DISABLE_PASSWORD_RESET_RATE_LIMIT=true" >> .env
php artisan config:clear

# 2. Wait 1 hour

# 3. Check logs
grep "victim@example.com" storage/logs/email.log | wc -l
# Result: 87 requests in 1 hour!

# 4. Check IPs
grep "victim@example.com" storage/logs/email.log | grep -o '"ip":"[^"]*"' | sort | uniq
# Result: 15 different IPs - distributed attack!
```

### Solution
```bash
# This is an attack on victim@example.com
# Option 1: Silently ignore (don't actually send email)
# Option 2: Add CAPTCHA to frontend
# Option 3: Contact victim@example.com to inform them

# Re-enable protection
echo "LOG_EMAILS=false" >> .env
echo "DISABLE_PASSWORD_RESET_RATE_LIMIT=false" >> .env
php artisan config:clear
```

---

## 💡 Pro Tips

1. **Use grep with timestamps** to see patterns:
   ```bash
   grep PASSWORD_RESET_REQUEST storage/logs/email.log | \
     awk '{print $1, $2}' | \
     uniq -c
   ```

2. **Export to CSV** for Excel analysis:
   ```bash
   grep PASSWORD_RESET_REQUEST storage/logs/email.log | \
     grep -o '"email":"[^"]*","timestamp":"[^"]*","ip":"[^"]*"' | \
     sed 's/"email":"//g; s/","timestamp":"/,/g; s/","ip":"/,/g; s/"//g' \
     > password_reset_analysis.csv
   ```

3. **Set up alerts** (optional):
   ```bash
   # Alert if more than 50 requests in last hour
   COUNT=$(grep PASSWORD_RESET_REQUEST storage/logs/email-$(date +%Y-%m-%d).log | tail -n 1000 | wc -l)
   if [ $COUNT -gt 50 ]; then
     echo "ALERT: High password reset activity detected!"
     # Send email/Slack notification
   fi
   ```

---

This debugging mode gives you full visibility while temporarily removing restrictions to identify the source of the problem.

