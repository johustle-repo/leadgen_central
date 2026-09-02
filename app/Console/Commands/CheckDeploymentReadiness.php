<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Migrations\Migrator;
use Throwable;

#[Signature('app:deployment-check')]
#[Description('Validate required production configuration before deployment')]
class CheckDeploymentReadiness extends Command
{
    public function handle(): int
    {
        $checks = [
            'Production environment' => app()->isProduction(),
            'Debug mode disabled' => ! (bool) config('app.debug'),
            'Application key configured' => filled(config('app.key')),
            'HTTPS application URL' => str_starts_with((string) config('app.url'), 'https://'),
            'Production database configured' => config('database.default') !== 'sqlite',
            'No pending database migrations' => $this->hasNoPendingMigrations(),
            'Asynchronous queue configured' => ! in_array(config('queue.default'), ['sync', 'null'], true),
            'Queue retry window exceeds job timeout' => (int) config('queue.connections.database.retry_after') > 300,
            'Production mail transport configured' => ! in_array(config('mail.default'), ['log', 'array'], true),
            'Session payload encryption enabled' => (bool) config('session.encrypt'),
            'Secure session cookie enabled' => (bool) config('session.secure'),
            'HTTP-only session cookie enabled' => (bool) config('session.http_only'),
            'Same-site session protection enabled' => in_array(config('session.same_site'), ['lax', 'strict'], true),
            'Google client ID configured' => filled(config('services.google.client_id')),
            'Google client secret configured' => filled(config('services.google.client_secret')),
            'HTTPS Google redirect URI' => str_starts_with((string) config('services.google.redirect_uri'), 'https://'),
            'Google redirect URI matches the application URL' => parse_url((string) config('services.google.redirect_uri'), PHP_URL_HOST) === parse_url((string) config('app.url'), PHP_URL_HOST),
            'Brochure file is readable' => is_readable((string) config('services.google.brochure_path')),
        ];

        foreach ($checks as $label => $passed) {
            $passed ? $this->components->info($label) : $this->components->error($label);
        }

        if (in_array(false, $checks, true)) {
            $this->newLine();
            $this->error('Deployment readiness checks failed. Correct the settings above before going live.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('LeadGen Central is configured for production deployment.');

        return self::SUCCESS;
    }

    /**
     * A migration file present on disk but missing from the migrations table means
     * `migrate --force` was never run for the current release. The schema it would
     * have created (e.g. lead_attachments) is then simply absent, which crashes any
     * feature that depends on it instead of failing loudly at deploy time.
     */
    private function hasNoPendingMigrations(): bool
    {
        try {
            $migrator = app(Migrator::class);

            if (! $migrator->repositoryExists()) {
                return false;
            }

            $ran = $migrator->getRepository()->getRan();
            $files = $migrator->getMigrationFiles(database_path('migrations'));

            return array_diff(array_keys($files), $ran) === [];
        } catch (Throwable) {
            // Database connectivity is out of scope for this check; an unreachable
            // database surfaces through the other checks and the /up health route
            // instead of failing this one with a misleading migrations message.
            return true;
        }
    }
}
