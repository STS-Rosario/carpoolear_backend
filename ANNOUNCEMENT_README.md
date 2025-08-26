# Carpoolear Announcement System

This system allows you to send push notifications to all users of the Carpoolear application. It supports external links, batching, and various targeting options.

## Features

- ✅ Send push notifications to all users
- ✅ Support for external links (e.g., crowdfunding pages)
- ✅ Batch processing to avoid overwhelming FCM
- ✅ Rate limiting to prevent abuse
- ✅ User targeting options (active users, device activity)
- ✅ Dry run mode for testing
- ✅ Comprehensive logging and error handling
- ✅ Statistics and reporting

## Quick Start

### Basic Usage

```bash
# Send a simple announcement
php artisan announcement:send "¡Nueva funcionalidad disponible!"

# Send with custom title
php artisan announcement:send "¡Nueva funcionalidad disponible!" --title="Actualización"

# Send with external link
php artisan announcement:send "¡Ayúdanos a crecer!" --url="https://crowdfunding.carpoolear.com"

# Send to specific users by ID
php artisan announcement:send "¡Prueba especial para usuarios seleccionados!" --user-ids="123,456,789"

### Advanced Usage

```bash
# Send only to active users (last 30 days)
php artisan announcement:send "¡Nueva funcionalidad disponible!" --active-only

# Send only to devices with recent activity (last 7 days)
php artisan announcement:send "¡Nueva funcionalidad disponible!" --device-activity-days=7

# Use smaller batch size for slower processing
php artisan announcement:send "¡Nueva funcionalidad disponible!" --batch-size=50

# Dry run to see what would be sent
php artisan announcement:send "¡Nueva funcionalidad disponible!" --dry-run

# Send to specific users with external link
php artisan announcement:send "¡Invitación especial!" --user-ids="123,456,789" --url="https://evento.carpoolear.com"

## Command Options

| Option | Description | Default |
|--------|-------------|---------|
| `message` | The announcement message to send | Required |
| `--title` | The title of the announcement | "Carpoolear" |
| `--url` | External URL to include in the notification | None |
| `--batch-size` | Number of users to process in each batch | 100 |
| `--dry-run` | Show what would be sent without actually sending | false |
| `--active-only` | Only send to users with recent activity (last 30 days) | false |
| `--device-activity-days` | Only send to devices with activity within X days (0 = all devices) | 0 |
| `--user-ids` | Comma-separated list of specific user IDs to target | None |

## Best Practices

### 1. **Use Batching**
Always use batching to avoid overwhelming Firebase Cloud Messaging (FCM). The default batch size of 100 is usually good, but you can adjust based on your needs.

```bash
# For large user bases, use smaller batches
php artisan announcement:send "Message" --batch-size=50
```

### 2. **Target Active Users**
For important announcements, target only active users to ensure better delivery rates.

```bash
php artisan announcement:send "Important update" --active-only
```

### 3. **Use Dry Run First**
Always test with dry run before sending to all users.

```bash
php artisan announcement:send "Test message" --dry-run
```

### 4. **External Links**
When including external links (like crowdfunding pages), make sure they are HTTPS and properly formatted.

```bash
php artisan announcement:send "¡Ayúdanos a crecer!" --url="https://crowdfunding.carpoolear.com"
```

### 5. **Rate Limiting**
The system includes rate limiting (max 10 announcements per hour) to prevent abuse. Plan your announcements accordingly.

### 6. **Monitor Results**
Always check the command output for statistics and error rates.

### 7. **Target Specific Users**
Use the `--user-ids` option to send announcements to specific users for testing or targeted campaigns.

```bash
# Test with specific users
php artisan announcement:send "Test message" --user-ids="123,456,789" --dry-run

# Send to VIP users
php artisan announcement:send "¡Contenido exclusivo para ti!" --user-ids="1,5,10,15,20"
```

## Use Cases

### Crowdfunding Campaign
```bash
php artisan announcement:send "¡Ayúdanos a hacer Carpoolear aún mejor! Tu apoyo es fundamental para seguir creciendo." --title="Crowdfunding Carpoolear" --url="https://crowdfunding.carpoolear.com" --active-only
```

### App Update
```bash
php artisan announcement:send "Nueva versión disponible con mejoras de rendimiento y nuevas funcionalidades." --title="Actualización Disponible" --url="https://carpoolear.com.ar/app"
```

### Maintenance Notice
```bash
php artisan announcement:send "El sistema estará en mantenimiento mañana de 2:00 a 4:00 AM. Gracias por tu paciencia." --title="Mantenimiento Programado"
```

### Feature Announcement
```bash
php artisan announcement:send "¡Ya puedes compartir tu ubicación en tiempo real con tus compañeros de viaje!" --title="Nueva Funcionalidad"
```

### Targeted User Campaign
```bash
php artisan announcement:send "¡Invitación especial al evento de lanzamiento!" --title="Evento Exclusivo" --user-ids="123,456,789,101,202" --url="https://evento.carpoolear.com"
```

### Beta Testing
```bash
php artisan announcement:send "¡Eres parte de nuestro grupo beta! Prueba la nueva funcionalidad antes que nadie." --title="Beta Tester" --user-ids="1,2,3,4,5,6,7,8,9,10"
```

## Technical Details

### How It Works

1. **User Filtering**: The system filters users based on:
   - Active status (`active = true`)
   - Not banned (`banned = false`)
   - Recent activity (if `--active-only` is used)
   - Device activity (if `--device-activity-days` is specified)
   - Specific user IDs (if `--user-ids` is provided)

2. **Device Filtering**: For each user, it checks for devices that:
   - Have notifications enabled (`notifications = true`)
   - Have recent activity (if specified)

3. **Notification Sending**: Uses the existing Firebase FCM infrastructure through:
   - `AnnouncementNotification` class
   - `PushChannel` for web push notifications
   - `DatabaseChannel` for in-app notifications

4. **Rate Limiting**: Built-in rate limiting prevents sending more than 10 announcements per hour.

### External Links Support

External links are supported through the `click_action` field in FCM notifications. When a user taps the notification:

- If an external URL is provided, it will open in the browser
- If no URL is provided, it will open the app's home screen
- The app can handle the external URL through the notification extras

### Error Handling

The system includes comprehensive error handling:

- Individual user failures don't stop the entire process
- All errors are logged with context
- Failed notifications are counted and reported
- Rate limiting prevents system overload

## Monitoring and Logs

### Command Output
The command provides real-time feedback:
- User statistics before sending
- Progress updates during sending
- Final summary with success/failure counts

### Log Files
Check Laravel logs for detailed error information:
```bash
tail -f storage/logs/laravel.log
```

### Database Notifications
In-app notifications are stored in the database and can be viewed in the app.

## Troubleshooting

### Common Issues

1. **No users found**
   - Check if users have `active = true` and `banned = false`
   - Verify device activity filters aren't too restrictive

2. **High failure rate**
   - Check Firebase configuration
   - Verify device tokens are valid
   - Check network connectivity

3. **Rate limit exceeded**
   - Wait for the rate limit to reset (1 hour)
   - Plan announcements in advance

### Debug Mode
For debugging, you can check the notification system directly:

```php
// In tinker or a test script
$service = app(\STS\Services\AnnouncementService::class);
$stats = $service->getUserStats();
print_r($stats);

// Test sending to specific users
$result = $service->sendToUsers('123,456,789', 'Test message', ['title' => 'Test']);
print_r($result);
```

## Security Considerations

- Only authorized administrators should have access to this command
- Rate limiting prevents abuse
- All actions are logged
- External URLs should be validated
- User privacy is respected (no personal data in logs)

## Future Enhancements

Potential improvements for the future:
- Scheduled announcements
- A/B testing support
- Geographic targeting
- User segment targeting
- Rich media support (images, videos)
- Analytics and engagement tracking 