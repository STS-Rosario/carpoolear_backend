# Carpoolear Badge Evaluation System

This system allows you to evaluate and award badges to users based on various criteria. It includes an Artisan command for batch processing and supports multiple badge types.

## Features

- ✅ Evaluate badges for all users or filtered subsets
- ✅ Support for multiple badge types
- ✅ Batch processing to handle large user bases
- ✅ User filtering options (active users, specific IDs)
- ✅ Dry run mode for testing
- ✅ Comprehensive logging and error handling
- ✅ Statistics and reporting
- ✅ Scheduled daily evaluation

## Badge Types

### 1. **registration_duration**
Awarded to users who have been registered for a specified number of days.

**Rules:**
```json
{
    "type": "registration_duration",
    "days": 30
}
```

### 2. **donated_to_campaign**
Awarded to users who have donated to a specific campaign.

**Rules:**
```json
{
    "type": "donated_to_campaign",
    "campaign_id": 1
}
```

### 3. **total_donated**
Awarded to users who have donated a total amount above a threshold.

**Rules:**
```json
{
    "type": "total_donated",
    "amount": 1000
}
```

### 4. **monthly_donor**
Awarded to users who have recurring donations.

**Rules:**
```json
{
    "type": "monthly_donor"
}
```

### 5. **carpoolear_member**
Awarded to Carpoolear team members (hardcoded IDs: 3209, 3203).

**Rules:**
```json
{
    "type": "carpoolear_member"
}
```

## Quick Start

### Basic Usage

```bash
# Evaluate badges for all users
php artisan badges:evaluate

# Evaluate badges for specific users
php artisan badges:evaluate --user-ids="123,456,789"

# Evaluate only users with recent connections (last 30 days)
php artisan badges:evaluate --active-only

# Dry run to see what would be evaluated
php artisan badges:evaluate --dry-run
```

### Advanced Usage

```bash
# Evaluate only users with recent connections (custom period)
php artisan badges:evaluate --active-only --activity-days=7

# Use smaller batch size for slower processing
php artisan badges:evaluate --batch-size=50

# Combine multiple filters
php artisan badges:evaluate --user-ids="123,456" --active-only --dry-run
```

## Command Options

| Option | Description | Default |
|--------|-------------|---------|
| `--user-ids` | Comma-separated list of specific user IDs to evaluate | None |
| `--active-only` | Only evaluate users with recent activity | false |
| `--activity-days` | Days to consider for active users | 30 |
| `--batch-size` | Number of users to process in each batch | 100 |
| `--dry-run` | Show what would be evaluated without actually awarding | false |

## Best Practices

### 1. **Use Dry Run First**
Always test with dry run before evaluating badges for all users.

```bash
php artisan badges:evaluate --dry-run
```

### 2. **Target Specific Users**
Use the `--user-ids` option to test with specific users before running on the entire user base.

```bash
# Test with specific users
php artisan badges:evaluate --user-ids="123,456,789" --dry-run

# Evaluate only team members
php artisan badges:evaluate --user-ids="3209,3203"
```

### 3. **Use Batching**
The default batch size of 100 is usually good, but you can adjust based on your server performance.

```bash
# For slower servers, use smaller batches
php artisan badges:evaluate --batch-size=50
```

### 4. **Filter Active Users**
For better performance, evaluate only users with recent connections when possible.

```bash
php artisan badges:evaluate --active-only --activity-days=7
```

## Use Cases

### Team Member Badges
```bash
# Award carpoolear_member badges to team
php artisan badges:evaluate --user-ids="3209,3203"
```

### Registration Duration Badges
```bash
# Evaluate registration duration badges for all users
php artisan badges:evaluate

# Or only for active users
php artisan badges:evaluate --active-only
```

### Campaign Donation Badges
```bash
# After a campaign ends, evaluate donation badges
php artisan badges:evaluate --active-only
```

### Testing New Badge Types
```bash
# Test new badge logic with specific users
php artisan badges:evaluate --user-ids="123,456" --dry-run
```

## Scheduled Evaluation

The system automatically runs badge evaluation daily at 2:00 AM (Argentina timezone) via the Laravel scheduler:

```php
// In app/Console/Kernel.php
$schedule->command('badges:evaluate')->dailyAt('02:00')->timezone('America/Argentina/Buenos_Aires');
```

## Creating Badges

Use the admin API to create badges:

```bash
# Create a carpoolear_member badge
curl -X POST /api/admin/badges \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Carpoolear Team Member",
    "slug": "carpoolear-team",
    "description": "Member of the Carpoolear development team",
    "rules": {
      "type": "carpoolear_member"
    }
  }'

# Create a registration duration badge
curl -X POST /api/admin/badges \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Early Adopter",
    "slug": "early-adopter",
    "description": "Member for 30+ days",
    "rules": {
      "type": "registration_duration",
      "days": 30
    }
  }'
```

## Technical Details

### How It Works

1. **User Filtering**: The system filters users based on:
   - Active status (`active = true`)
   - Not banned (`banned = false`)
   - Recent connections (if `--active-only` is used, based on `last_connection`)
   - Specific user IDs (if `--user-ids` is provided)

2. **Badge Evaluation**: For each user, it checks:
   - All available badges in the system
   - Whether the user meets the badge criteria
   - Whether the user already has the badge

3. **Badge Awarding**: When criteria are met:
   - Badge is attached to the user
   - `awarded_at` timestamp is recorded
   - Action is logged

4. **Batch Processing**: Users are processed in configurable batches to:
   - Avoid memory issues with large user bases
   - Provide progress feedback
   - Handle errors gracefully

### Error Handling

The system includes comprehensive error handling:

- Individual user failures don't stop the entire process
- All errors are logged with context
- Failed evaluations are counted and reported
- Progress continues even with some failures

## Monitoring and Logs

### Command Output
The command provides real-time feedback:
- User statistics before evaluation
- Progress updates during processing
- Final summary with success/failure counts

### Log Files
Check Laravel logs for detailed error information:
```bash
tail -f storage/logs/laravel.log
```

### Database Records
Badge awards are stored in the `user_badges` table with:
- `user_id` and `badge_id`
- `awarded_at` timestamp
- Creation and update timestamps

## Troubleshooting

### Common Issues

1. **No users found**
   - Check if users have `active = true` and `banned = false`
   - Verify activity filters aren't too restrictive

2. **High error rate**
   - Check database connectivity
   - Verify badge rules are valid
   - Check user model relationships

3. **Memory issues**
   - Reduce batch size with `--batch-size=50`
   - Use `--active-only` to limit user scope

### Debug Mode
For debugging, you can check the badge system directly:

```php
// In tinker or a test script
$user = \STS\Models\User::find(1);
$badgeEvaluator = new \STS\Services\BadgeEvaluatorService();
$badgeEvaluator->evaluate($user);

// Check user's badges
$user->badges()->get();
```

## Security Considerations

- Only authorized administrators should have access to this command
- All actions are logged
- User privacy is respected (no personal data in logs)
- Badge rules are validated before processing

## Future Enhancements

Potential improvements for the future:
- Geographic targeting
- User segment targeting
- Badge expiration
- Badge levels (bronze, silver, gold)
- Analytics and engagement tracking
- Webhook notifications for badge awards
