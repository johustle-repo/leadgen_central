<?php

it('adds browser security headers to application responses', function () {
    $response = $this->get('/up');

    $response
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()')
        ->assertHeaderMissing('Strict-Transport-Security');
});

it('adds strict transport security to production HTTPS responses', function () {
    app()->detectEnvironment(fn (): string => 'production');

    $response = $this->withServerVariables(['HTTPS' => 'on', 'SERVER_PORT' => 443])->get('https://localhost/up');

    $response
        ->assertOk()
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');

    app()->detectEnvironment(fn (): string => 'testing');
});
