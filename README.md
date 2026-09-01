# LeadGen Central

LeadGen Central is a Laravel 13, Inertia 3, React 19, MySQL-compatible lead operations system. It provides role-based lead ownership, manual and bulk lead intake, queued CSV cleaning, duplicate review, timezone normalization, verification workflows, Gmail reply synchronization and classification, email sequences, audit logs, and administration.

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

Use [DEPLOYMENT.md](DEPLOYMENT.md) and `.env.production.example` for the complete production checklist. Before going live, run `php artisan app:deployment-check`; it fails when critical production settings are unsafe or incomplete.
