<?php

use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Schedule;

it('fails deployment checks when production configuration is unsafe', function () {
    config([
        'app.debug' => true,
        'app.key' => null,
        'app.url' => 'http://localhost',
        'database.default' => 'sqlite',
        'queue.default' => 'sync',
        'mail.default' => 'log',
        'session.secure' => false,
    ]);

    $this->artisan('app:deployment-check')
        ->expectsOutputToContain('Deployment readiness checks failed')
        ->assertFailed();
});

it('passes deployment checks with complete production configuration', function () {
    $originalDatabase = config('database.default');
    app()->detectEnvironment(fn (): string => 'production');
    config([
        'app.debug' => false,
        'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
        'app.url' => 'https://leadgen.example.com',
        'database.default' => 'mysql',
        'queue.default' => 'database',
        'queue.connections.database.retry_after' => 360,
        'mail.default' => 'smtp',
        'session.encrypt' => true,
        'session.secure' => true,
        'session.http_only' => true,
        'session.same_site' => 'lax',
        'services.google.client_id' => 'google-client-id',
        'services.google.client_secret' => 'google-client-secret',
        'services.google.redirect_uri' => 'https://leadgen.example.com/integrations/gmail/callback',
        'services.google.brochure_path' => base_path('README.md'),
    ]);

    $this->artisan('app:deployment-check')
        ->expectsOutputToContain('LeadGen Central is configured for production deployment')
        ->assertSuccessful();

    config(['database.default' => $originalDatabase]);
    app()->detectEnvironment(fn (): string => 'testing');
});

it('fails deployment checks when the Google redirect URI does not match the application URL', function () {
    // Regression coverage: a stale local/testing GOOGLE_REDIRECT_URI (e.g. still
    // pointing at http://127.0.0.1:8000) left in the production .env sends agents
    // through Google OAuth and back to an address their browser cannot reach, which
    // surfaces as the app appearing to redirect them to that unreachable host.
    $originalDatabase = config('database.default');
    app()->detectEnvironment(fn (): string => 'production');
    config([
        'app.debug' => false,
        'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
        'app.url' => 'https://leadgen.example.com',
        'database.default' => 'mysql',
        'queue.default' => 'database',
        'queue.connections.database.retry_after' => 360,
        'mail.default' => 'smtp',
        'session.encrypt' => true,
        'session.secure' => true,
        'session.http_only' => true,
        'session.same_site' => 'lax',
        'services.google.client_id' => 'google-client-id',
        'services.google.client_secret' => 'google-client-secret',
        'services.google.redirect_uri' => 'http://127.0.0.1:8000/integrations/gmail/callback',
        'services.google.brochure_path' => base_path('README.md'),
    ]);

    $this->artisan('app:deployment-check')
        ->expectsOutputToContain('Deployment readiness checks failed')
        ->assertFailed();

    config(['database.default' => $originalDatabase]);
    app()->detectEnvironment(fn (): string => 'testing');
});

it('schedules recurring tasks as in-process callbacks instead of shelling out', function () {
    // Schedule::command()/exec() shell out via Symfony Process (proc_open), which
    // shared hosts such as Hostinger disable. Every recurring task must run through
    // Schedule::call()/Artisan::call() so it still executes when proc_open is unavailable.
    $events = collect(app(Schedule::class)->events());

    expect($events)->not->toBeEmpty();

    foreach ($events as $event) {
        expect($event)->toBeInstanceOf(CallbackEvent::class);
    }

    $names = $events->pluck('description');

    expect($names)
        ->toContain('queue-work-database')
        ->toContain('uploads-dispatch-pending')
        ->toContain('gmail-sync')
        ->toContain('email-sequences-process');
});
