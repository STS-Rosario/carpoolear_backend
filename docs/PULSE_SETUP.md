# Laravel Pulse Setup

Laravel Pulse provides a real-time performance dashboard for the Carpoolear backend at `/pulse`.

## What Pulse shows

- **Slow requests** — API endpoints exceeding the configured threshold
- **Slow queries** — heavy SQL with source location
- **Exceptions** — frequency and recency of application errors
- **Queues** — throughput and failures (works with the `database` queue driver and `emails` queue)
- **Slow jobs** — long-running queued jobs
- **Slow outgoing requests** — external HTTP calls made via Laravel's `Http` facade (MercadoPago, Mapbox, Google, SMS, etc.)
- **Cache** — hit/miss statistics
- **Application usage** — most active users by requests and jobs
- **Servers** — CPU, memory, and disk (requires `pulse:check` via Supervisor)

## What Pulse does not show

- Scheduled Artisan command performance (`rating:availables`, `live-location:process`, etc.)
- Raw Guzzle HTTP calls (for example `BuildNodes`); only `Http::` facade calls are tracked
- Mobile app or frontend metrics
- Business KPIs (trips created, signups, etc.)

## Admin access

Pulse is admin-only (`is_admin = true`).

1. Open `/pulse/login`
2. Sign in with an admin user's email and password (same credentials as the API)
3. After login you are redirected to `/pulse`
4. Sign out with `POST /pulse/logout`

Non-admin users, banned users, and inactive users cannot sign in to Pulse even with valid credentials.

Login is rate-limited to 6 attempts per minute.

## Environment variables

```env
PULSE_ENABLED=true
```

Optional (not required for v1):

```env
# PULSE_DB_CONNECTION=pulse
# PULSE_INGEST_DRIVER=redis
# PULSE_REDIS_CONNECTION=pulse
```

Disable recording entirely with `PULSE_ENABLED=false` (useful in CI or local dev if desired).

## Production deploy checklist

1. Deploy code with `laravel/pulse` installed
2. Set `PULSE_ENABLED=true` in production `.env`
3. Run migrations: `php artisan migrate --force`
4. Clear/rebuild config cache: `php artisan config:cache`
5. Restart Pulse worker: `php artisan pulse:restart`
6. Ensure `storage/` and cache directories are writable by PHP-FPM (`www-data`) — see [PRODUCTION_STORAGE_AND_CACHE.md](PRODUCTION_STORAGE_AND_CACHE.md)
7. Add Supervisor program for `pulse:check` (see below)
8. Smoke test: sign in at `/pulse/login` and confirm the dashboard loads

`deploy.sh` and `after_deploy.sh` already run `migrate --force` and `pulse:restart`.

## Supervisor: `pulse:check` (step by step)

The **Servers** card in Pulse needs a long-running `pulse:check` process on each machine you want to monitor. That process samples CPU, memory, and disk usage and writes the results to Pulse's database.

**Important:** `routes/console.php` also schedules `pulse:check` every minute, but that only helps if Laravel's scheduler is running (`* * * * * php artisan schedule:run`). Even then, `pulse:check` is designed to stay alive and report continuously — **Supervisor is the correct way to run it in production.**

`pulse:restart` (run on deploy via `after_deploy.sh`) tells an already-running `pulse:check` process to exit so Supervisor starts a fresh one with the new code. It does **not** start the process if Supervisor is not configured.

### What you need before starting

| Item | Typical Carpoolear value | How to confirm |
|------|--------------------------|----------------|
| App root on server | `/home/movilizame/sites/carpoolear_dev` | Same directory you `cd` into for deploys |
| PHP binary | `/usr/bin/php` | `which php` on the server |
| Process user | `www-data` | Same user as PHP-FPM / queue workers — see [PRODUCTION_STORAGE_AND_CACHE.md](PRODUCTION_STORAGE_AND_CACHE.md) |
| Supervisor installed | yes | `sudo supervisorctl version` |
| Pulse deployed | migrations run, `PULSE_ENABLED=true` | `php artisan pulse:check --help` should work |

Use the **same app path and `www-data` user** as your existing queue worker Supervisor program (see [PASSWORD_RESET_ERROR_SOLUTION.md](PASSWORD_RESET_ERROR_SOLUTION.md)).

---

### Step 1 — SSH into the app server

```bash
ssh movilizame@104.131.15.228 -p 2200
cd /home/movilizame/sites/carpoolear_dev
```

Replace host, port, and path with your real production values.

---

### Step 2 — Confirm `pulse:check` works manually

Run it in the foreground for a few seconds, then stop with `Ctrl+C`:

```bash
php artisan pulse:check
```

You should see no errors. If you get a database or config error, fix that before continuing (run `php artisan migrate --force`, check `PULSE_ENABLED=true`, run `php artisan config:cache`).

Optional sanity check:

```bash
php artisan pulse:check --help
```

---

### Step 3 — Create the Supervisor program file

On Ubuntu/Debian, program configs usually live under `/etc/supervisor/conf.d/`.

```bash
sudo nano /etc/supervisor/conf.d/carpoolear-pulse-check.conf
```

Paste the following and **replace `/home/movilizame/sites/carpoolear_dev`** with your app root if different:

```ini
[program:carpoolear-pulse-check]
process_name=%(program_name)s
command=/usr/bin/php /home/movilizame/sites/carpoolear_dev/artisan pulse:check
directory=/home/movilizame/sites/carpoolear_dev
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/home/movilizame/sites/carpoolear_dev/storage/logs/pulse-check.log
stdout_logfile_maxbytes=10MB
stdout_logfile_backups=3
stopwaitsecs=10
```

**Why these options matter:**

- `directory=` — ensures relative paths and `.env` resolve correctly
- `user=www-data` — matches PHP-FPM; avoids permission issues on `storage/logs`
- `stopasgroup` / `killasgroup` — clean shutdown when Supervisor restarts the process
- `numprocs=1` — only one `pulse:check` per server (do not scale this horizontally on the same host)
- `stdout_logfile=` — if the process crashes, check this file first

If `which php` returns a different path, update `command=` accordingly.

---

### Step 4 — Ensure the log file is writable

```bash
sudo touch /home/movilizame/sites/carpoolear_dev/storage/logs/pulse-check.log
sudo chown www-data:www-data /home/movilizame/sites/carpoolear_dev/storage/logs/pulse-check.log
```

If `storage/logs` itself is not writable by `www-data`, follow the permission steps in [PRODUCTION_STORAGE_AND_CACHE.md](PRODUCTION_STORAGE_AND_CACHE.md).

---

### Step 5 — Load the new Supervisor config

```bash
sudo supervisorctl reread
sudo supervisorctl update
```

Expected output includes something like:

```text
carpoolear-pulse-check: added process group
```

---

### Step 6 — Start the program and verify status

```bash
sudo supervisorctl start carpoolear-pulse-check
sudo supervisorctl status carpoolear-pulse-check
```

Expected status: **`RUNNING`**.

Other useful commands:

```bash
# Full status of all programs
sudo supervisorctl status

# Follow logs live
sudo tail -f /home/movilizame/sites/carpoolear_dev/storage/logs/pulse-check.log
```

---

### Step 7 — Confirm data appears in Pulse

1. Sign in at `https://your-domain/pulse/login` with an admin account
2. Open the **Servers** card at the top of the dashboard
3. Within a minute or two you should see this host (hostname from `PULSE_SERVER_NAME` or the server's system hostname)

To set a friendly server name in `.env`:

```env
PULSE_SERVER_NAME=carpoolear-prod-1
```

Then run `php artisan config:cache` and `php artisan pulse:restart`.

---

### Step 8 — How deploys interact with Supervisor

`after_deploy.sh` and `deploy.sh` already run:

```bash
php artisan pulse:restart
```

**Deploy flow:**

1. Code is pulled / deployed
2. `php artisan migrate --force` runs
3. `php artisan config:cache` runs
4. `php artisan pulse:restart` signals the running `pulse:check` to stop
5. Supervisor sees the process exited and **automatically starts a new one** (`autorestart=true`)

You do **not** need to run `supervisorctl restart` on every deploy unless `pulse:restart` fails (for example cache not writable).

Manual restart after config changes:

```bash
sudo supervisorctl restart carpoolear-pulse-check
```

---

### Step 9 — Multiple servers (if applicable)

Run **one** `carpoolear-pulse-check` Supervisor program **on each application server** you want in the Servers card. Give each server a unique name:

```env
# Server A .env
PULSE_SERVER_NAME=carpoolear-web-1

# Server B .env
PULSE_SERVER_NAME=carpoolear-web-2
```

All can write to the same Pulse database; the dashboard aggregates them.

---

### Troubleshooting

| Symptom | Likely cause | What to do |
|---------|--------------|------------|
| Status `FATAL` or `BACKOFF` | Wrong path, PHP error, or permissions | Read `storage/logs/pulse-check.log`; run `php artisan pulse:check` manually as `www-data` |
| Status `RUNNING` but Servers card empty | `PULSE_ENABLED=false` or DB issue | Check `.env`, `php artisan config:clear && php artisan config:cache` |
| Process keeps restarting | Crash on boot (missing tables, bad config) | `php artisan migrate --force`; check Laravel log in `storage/logs/laravel.log` |
| `pulse:restart` has no effect | Cache driver not writable | Fix file cache permissions per production runbook; or use `supervisorctl restart` |
| Log permission denied | `www-data` cannot write log file | Repeat Step 4; fix `storage/logs` ownership |

Run `pulse:check` as the same user Supervisor uses:

```bash
sudo -u www-data -H bash -lc 'cd /home/movilizame/sites/carpoolear_dev && php artisan pulse:check'
```

Stop with `Ctrl+C` once you confirm it starts cleanly.

---

### Stopping or removing the program

Temporary stop (keeps config):

```bash
sudo supervisorctl stop carpoolear-pulse-check
```

Remove permanently:

```bash
sudo supervisorctl stop carpoolear-pulse-check
sudo rm /etc/supervisor/conf.d/carpoolear-pulse-check.conf
sudo supervisorctl reread
sudo supervisorctl update
```

The rest of Pulse (slow requests, exceptions, queues, etc.) continues to work without `pulse:check`; only the **Servers** card will be empty.

---

### Coexistence with the queue worker

You likely already have a program similar to this for emails:

```ini
[program:carpoolear-queue-worker]
command=php /home/movilizame/sites/carpoolear_dev/artisan queue:work --queue=emails ...
user=www-data
```

`carpoolear-pulse-check` is a **separate** Supervisor program. Both can run at the same time. They serve different purposes:

| Program | Command | Purpose |
|---------|---------|---------|
| `carpoolear-queue-worker` | `queue:work` | Process queued jobs (emails, etc.) |
| `carpoolear-pulse-check` | `pulse:check` | Report server CPU/RAM/disk to Pulse |

Do not merge them into a single Supervisor entry.

## Carpoolear-specific tuning

Configured in `config/pulse.php`:

- Slow requests ignore `/up`, `/pulse`, and `/app/` paths
- Admin API routes (`/api/admin/*`) use a 3s slow threshold
- Outgoing requests are grouped by provider (mapbox, google, mercadopago, sms)
- `SyncArgautosCarCatalogJob` uses a 120s slow-job threshold
- Notification count cache keys are grouped per user

## Future upgrades

If Pulse DB writes become noticeable under high traffic:

1. Set `PULSE_INGEST_DRIVER=redis` with a dedicated Redis connection
2. Run `php artisan pulse:work` via Supervisor (separate from `pulse:check`)
3. Optionally use `PULSE_DB_CONNECTION` for a dedicated MySQL database

## Security notes

- Pulse uses web session auth, separate from JWT API auth
- Only `is_admin` users pass the `viewPulse` gate
- Do not expose `/pulse` without HTTPS in production
- Consider IP allowlisting at the web server if you want an extra layer beyond admin login

## Related docs

- [Laravel Pulse documentation](https://laravel.com/docs/pulse)
- [PASSWORD_RESET_ERROR_SOLUTION.md](PASSWORD_RESET_ERROR_SOLUTION.md) — existing Supervisor queue worker example
- [PRODUCTION_STORAGE_AND_CACHE.md](PRODUCTION_STORAGE_AND_CACHE.md) — file cache permissions on production
