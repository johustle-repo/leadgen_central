# LeadGen Central

LeadGen Central is a Laravel 13, Inertia 3, React 19, MySQL-compatible lead generation, validation, and intelligence management system. Phase 1 provides authenticated role-based dashboards, lead ownership, manual entry, CSV mapping and queued processing, row-level upload results, user administration, and basic settings.

## Requirements

- PHP 8.3 or newer with Laravel's required extensions
- Composer
- Node.js and npm
- MySQL 8+ for deployment (SQLite is supported for local tests)

## Installation

```bash
composer install
copy .env.example .env
php artisan key:generate
npm install
php artisan migrate --seed
npm run build
```

Configure the `DB_*` environment values before migrating. Development seed accounts use the emails and password supplied through `LEADGEN_*` values; change them before seeding a shared environment. No production credential is embedded in application code.

## Development

Run the web server, queue worker, and Vite together:

```bash
composer run dev
```

Alternatively run `php artisan serve`, `php artisan queue:work --tries=3`, and `npm run dev` separately. CSV imports require a queue worker unless `QUEUE_CONNECTION=sync` is selected locally.

Useful verification commands:

```bash
php artisan test --compact
vendor/bin/pint --format agent
composer run types:check
npm run types:check
npm run build
```

## Production

- Point the web root to `public/` and run `php artisan migrate --force` during deployment.
- Use MySQL and supervise a persistent queue worker.
- Set `APP_ENV=production`, `APP_DEBUG=false`, secure application/database/mail values, and unique seed credentials.
- Run `php artisan optimize`; ensure `storage/` and `bootstrap/cache/` are writable.
- Uploaded CSV files use Laravel's local disk. Configure durable storage if deployments replace local files.

## Phase 1 boundaries

Advanced duplicate intelligence, timezone matching, verification workflows, scraping, AI enrichment, and advanced analytics are intentionally reserved for later phases.
