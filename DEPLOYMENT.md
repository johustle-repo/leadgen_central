# LeadGen Central deployment

## Production requirements

- PHP 8.3 or newer with the extensions required by Laravel, MySQL, cURL, and file uploads
- MySQL 8 or compatible managed database
- Node.js 22 for the asset build
- A process supervisor for the Laravel queue worker
- A cron service for the Laravel scheduler
- HTTPS, a persistent `storage` directory, and a persistent brochure file
- An SMTP provider and a Google Cloud OAuth web client with Gmail API enabled

Do not deploy SQLite, `APP_DEBUG=true`, the log mailer, or the synchronous queue driver.

## Environment

Copy `.env.production.example` to the platform's secret/environment manager and replace every placeholder. Generate the application key once with `php artisan key:generate --show`; keep that key stable across releases because Gmail tokens and other encrypted values depend on it.

Back up `APP_KEY` in a restricted secret manager. When rotating it, place the former key in `APP_PREVIOUS_KEYS` until all encrypted Gmail credentials have been rewritten with the new key. Losing every applicable key permanently makes encrypted integration tokens unreadable.

The Google OAuth redirect URI must exactly match the production callback:

```text
https://YOUR-DOMAIN/integrations/gmail/callback
```

Rotate the Google client secret that was used during development before deployment. Never commit the replacement secret. If the OAuth consent screen remains in external testing mode, every agent Gmail address must be registered as a test user; complete Google verification before opening access to users outside that list.

Store the DUSCAFF brochure at the absolute persistent path configured by `DUSCAFF_BROCHURE_PATH`. Uploaded CSV files are stored under `storage/app/private`, so that directory must survive releases or use a persistent mounted volume.

## First deployment

Run these commands from the release directory:

```bash
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
npm ci
npm run build
php artisan migrate --force
php artisan storage:link
php artisan optimize
php artisan app:deployment-check
```

Do not run `db:seed` in production. Create the first administrator through a controlled one-time process and use a unique password.

Point the web server document root to `public/`, not the repository root. Grant the web and worker users write access only to `storage/` and `bootstrap/cache/`.

## Required long-running processes

**On a VPS or dedicated host with a process supervisor** (systemd, Supervisor, etc.), run one or more supervised queue workers:

```bash
php artisan queue:work database --sleep=3 --tries=3 --timeout=300 --max-time=3600
```

**On shared hosting without supervisor access (e.g. Hostinger)**, there is no way to keep a `queue:work` process alive, so the app instead drains the queue from the scheduler itself every minute (see `routes/console.php`). No separate worker process is required in this mode — only the cron entry below.

Configure one cron entry on every scheduler host; the application uses single-server locks to prevent duplicate execution:

```cron
* * * * * cd /var/www/leadgen-central/current && php artisan schedule:run >> /dev/null 2>&1
```

The scheduler queues Gmail reply synchronization, processes email sequence steps, re-dispatches any lead upload that lost its queue job, and (on shared hosting) drains the database queue itself — all every minute. A supervised worker satisfies the same purpose on hosts that can run one.

Every scheduled task registers via `Schedule::call()`/`Artisan::call()`, not `Schedule::command()`/`Schedule::exec()`. The latter shell out through Symfony `Process` (`proc_open`), which many shared hosts (including Hostinger) disable outright — the task would silently never run even though `schedule:run` fires correctly every minute, which is why lead uploads could get stuck at "Pending" indefinitely. Running everything in-process via `Artisan::call()` avoids that dependency entirely. Verify the schedule is registered correctly on a fresh deployment with:

```bash
php artisan schedule:list
```

If a batch is still stuck "Pending" after deploying this fix, confirm the cron entry above is actually installed in the host's control panel (e.g. Hostinger's hPanel &rarr; Advanced &rarr; Cron Jobs) and that it points at the correct PHP binary and release path.

## Every release

```bash
php artisan down --retry=60
git pull --ff-only
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
npm ci
npm run build
php artisan migrate --force
php artisan optimize
php artisan reload
php artisan schedule:interrupt
php artisan up
php artisan app:deployment-check
```

Use atomic releases when the hosting platform supports them. Back up the database and persistent upload/brochure storage before migrations. Roll back by restoring the previous release and database backup; do not run destructive migration commands on production.

## Monitoring checklist

- Monitor `GET /up`; it verifies that Laravel boots and the database is reachable.
- Alert on failed jobs and review them with `php artisan queue:failed`.
- Monitor `storage/logs`, disk usage, queue age, scheduler execution, SMTP delivery, and Gmail OAuth errors.
- Confirm email verification, password reset, Gmail connect/sync, CSV upload, raw/cleaned downloads, and outbound brochure email after deployment.
- Verify secure cookies and HTTPS redirects in the browser.
- Review audit logs for unusual CSV export volume and repeated access attempts. Authenticated screens and exports are marked `no-store`, and export endpoints are rate limited per user.
- Restrict production database, upload storage, backups, logs, and the brochure to the application service account. Encrypt database and backup volumes at the hosting-provider layer.

## Pre-deployment verification

```bash
composer audit --locked
npm audit --audit-level=high
composer run ci:check
npm run build
php artisan optimize
php artisan app:deployment-check
```
