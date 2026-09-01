<?php

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
        'session.secure' => true,
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
