# Production / staging: storage, file cache, and rate limiting

This runbook covers a recurring Linux deployment issue: **PHP runs as `www-data`**, but **`storage` (or cache shards) are owned by the deploy user** without group write on every directory. Laravel’s **file** cache then fails when middleware (for example `throttle` on `/api/osrm/route/v1/...`) tries to create files under `storage/framework/cache/data/...`.

## Symptoms

- Log lines similar to:

  `fopen(.../storage/framework/cache/data/XX/YY/...): Failed to open stream: No such file or directory`

- Stack trace includes `Illuminate\Routing\Middleware\ThrottleRequests` and `Illuminate\Cache\FileStore`.

- Throttled routes (for example the OSRM proxy in `routes/api.php`) return **500** even though routing logic itself is fine.

## Confirm environment

```bash
cd /path/to/carpoolear_backend

php artisan tinker --execute="echo 'cache='.config('cache.default').PHP_EOL;"
```

If `cache=file`, file permissions on `storage` and `bootstrap/cache` must allow the web / queue user to create directories and files.

Who runs PHP:

```bash
ps -eo user,group,cmd | grep -E 'php-fpm|php8|apache2' | grep -v grep
```

Typical result: `www-data www-data` for `php-fpm` pool workers and Apache.

## Inspect a failing shard (optional)

Replace the path with the one from your log, or pick any shard under `data`:

```bash
namei -om storage/framework/cache/data/7c/58
ls -ld storage/framework/cache/data/7c
```

If a parent directory is `drwxr-sr-x` (mode `2755`) and owned by `deployuser:www-data`, **`www-data` cannot create new subdirectories** there (no group `w` on the directory). That matches the error.

## Fix (recommended on production / staging)

Run from the project root. Adjust `www-data` if your distro uses another user for FPM.

```bash
cd /path/to/carpoolear_backend

sudo chown -R www-data:www-data storage bootstrap/cache
sudo find storage bootstrap/cache -type d -exec chmod 2775 {} \;
sudo find storage bootstrap/cache -type f -exec chmod 664 {} \;

php artisan optimize:clear
```

- **`chown`**: web and queue workers can write consistently.
- **`2775` on directories**: group write + setgid so new files/dirs keep the correct group.
- **`664` on files**: predictable group-writable files.

Afterwards, re-hit a throttled endpoint or run:

```bash
curl -i 'https://your-host/api/osrm/route/v1/driving/LON1,LAT1;LON2,LAT2?overview=false'
```

Confirm logs no longer show `LockableFile` / `fopen` on cache paths.

## Staging servers

Apply the same rules whenever:

- Code is deployed or synced as a **non-`www-data`** user, and
- `CACHE_DRIVER=file` (or default file store), and
- Apache + `php-fpm` (or similar) runs as **`www-data`**.

Re-run the **chown + chmod + optimize:clear** block after deployments if you notice permission drift.

## Alternative: avoid file cache for rate limits

If you use **Redis** (or another shared store) for `CACHE_DRIVER` in `.env`, rate limiter and application cache no longer depend on writable tree under `storage/framework/cache/data` for those features. You still need correct permissions for `storage/logs`, sessions (if file-based), uploads, etc.

## Related application routes

- `GET api/osrm/route/v1/{path}` uses `throttle:180,1` and `OsrmProxyController`; failures in throttle cache I/O surface before the controller runs.

---

*Internal ops note: keep this doc in sync with your real deploy user and PHP-FPM pool user on each host.*
