<?php

use App\Models\User;

it('adds browser security headers to application responses', function () {
    $response = $this->get('/up');

    $response
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Permissions-Policy', 'camera=(self), microphone=(), geolocation=()')
        ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin')
        ->assertHeader('Cross-Origin-Resource-Policy', 'same-origin')
        ->assertHeader('Content-Security-Policy', "base-uri 'self'; form-action 'self'; frame-ancestors 'self'; object-src 'none'")
        ->assertHeaderMissing('Strict-Transport-Security');
});

it('prevents browsers and proxies from caching authenticated data', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response
        ->assertOk()
        ->assertHeader('Cache-Control', 'max-age=0, must-revalidate, no-store, private')
        ->assertHeader('Pragma', 'no-cache')
        ->assertHeader('Expires', '0');
});

it('adds strict transport security to production HTTPS responses', function () {
    app()->detectEnvironment(fn (): string => 'production');

    $response = $this->withServerVariables(['HTTPS' => 'on', 'SERVER_PORT' => 443])->get('https://localhost/up');

    $response
        ->assertOk()
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');

    app()->detectEnvironment(fn (): string => 'testing');
});
