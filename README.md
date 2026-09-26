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

## Deploying a release

Production deploys automatically. Every push to `main` runs the `ci` job in [`.github/workflows/tests.yml`](.github/workflows/tests.yml). When it passes, the `deploy` job connects to the Hostinger server over SSH and runs `~/deploy-leadgen.sh`. If CI fails, nothing is deployed.

To release a change:

1. Build the frontend locally with `npm run build`. The compiled `public/build` directory is committed to git.
2. Commit and push to `main`.
3. Follow the run under the repository's **Actions** tab. A successful `deploy` log ends with `LeadGen deployed ✅ (N pages in manifest)`.

The deploy script runs these steps:

1. **Guards:** it aborts before touching the site if the folder's `.env` is not LeadGen's, if a tracked file was edited on the server, or if stray files appear in `public/build`.
2. **Release:** maintenance mode, `git pull --ff-only`, `composer install --no-dev`, `migrate --force`.
3. **Checks:** it confirms every file listed in `public/build/manifest.json` exists.
4. **Finish:** `optimize:clear`, `optimize`, `reload`, then the site is brought back up. It always comes back up, even when a step fails.

### Rules

- Never run `npm run build` on the server. Build on your machine and commit `public/build`.
- Never edit files on the server. Every change goes through git.
- Add new `.env` values to the server by hand before pushing code that needs them.
- Keep other applications out of LeadGen's server folder.

### Manual deploy

To redeploy without pushing, run `~/deploy-leadgen.sh` in an SSH session on the server.

### Setup

The `deploy` job uses the `production` environment. It needs these secrets under **Settings → Environments → production**:

| Secret | Value |
| --- | --- |
| `DEPLOY_SSH_KEY` | Private key of a passphrase-less SSH key whose public half is added in hPanel → SSH Access → SSH Keys |
| `DEPLOY_KNOWN_HOSTS` | The server's host keys (`ssh-keyscan -p <port> <host>`), verified against a trusted login |
| `DEPLOY_HOST` | Server IP address |
| `DEPLOY_PORT` | SSH port |
| `DEPLOY_USER` | SSH username |

`~/deploy-leadgen.sh` lives on the server, outside the repository, so a `git pull` can never change it while it is running.

### Troubleshooting

| Deploy log message | Fix |
| --- | --- |
| `ABORT: not the LeadGen folder` | `APP_DIR` in the script points at the wrong folder. |
| `ABORT: files changed on server` | Make the change locally and push it, then run `git checkout -- <file>` on the server. |
| `ABORT: stray build files on server` | Run `git checkout -- public/build && git clean -fd public/build` on the server. |
| `ABORT: build files missing` | Run `npm run build` locally, commit `public/build`, and push. |
| Site shows "500 Server Error" | Read the cause with `grep "production.ERROR" storage/logs/laravel.log \| tail -n 3` on the server. |
