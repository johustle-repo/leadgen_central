<?php

use App\Models\User;

it('renders the saved accent colour on the page so it applies before scripts load', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->withUnencryptedCookie('accent', 'violet')->get(route('dashboard'))
        ->assertOk()->assertSee('data-accent="violet"', false);
});

it('falls back to the default accent for a missing or unknown cookie', function (?string $cookie) {
    $user = User::factory()->create();
    $request = $this->actingAs($user);
    if ($cookie !== null) {
        $request = $request->withUnencryptedCookie('accent', $cookie);
    }

    $request->get(route('dashboard'))->assertOk()->assertSee('data-accent="ocean"', false);
})->with(['missing' => [null], 'unknown' => ['"><script>']]);
