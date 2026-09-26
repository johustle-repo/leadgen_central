<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

it('lets a user upload, replace and remove their profile picture', function () {
    Storage::fake('local');
    $agent = User::factory()->create();

    $this->actingAs($agent)->post(route('avatar.update'), ['avatar' => UploadedFile::fake()->image('me.jpg', 200, 200)])
        ->assertRedirect(route('profile.edit'));
    $firstPath = $agent->refresh()->avatar_path;
    Storage::disk('local')->assertExists($firstPath);

    $this->actingAs($agent)->post(route('avatar.update'), ['avatar' => UploadedFile::fake()->image('me.png', 200, 200)]);
    Storage::disk('local')->assertMissing($firstPath);
    Storage::disk('local')->assertExists($agent->refresh()->avatar_path);

    $this->actingAs($agent)->delete(route('avatar.destroy'))->assertRedirect(route('profile.edit'));
    expect($agent->refresh()->avatar_path)->toBeNull();
});

it('rejects svg, tiny and oversized profile pictures', function (UploadedFile $file) {
    Storage::fake('local');
    $agent = User::factory()->create();

    $this->actingAs($agent)->post(route('avatar.update'), ['avatar' => $file])->assertSessionHasErrors('avatar');
    expect($agent->refresh()->avatar_path)->toBeNull();
})->with([
    'svg' => fn () => UploadedFile::fake()->create('logo.svg', 5, 'image/svg+xml'),
    'too small' => fn () => UploadedFile::fake()->image('tiny.png', 20, 20),
    'too large' => fn () => UploadedFile::fake()->image('huge.jpg', 400, 400)->size(3000),
]);

it('shares the avatar url with the page and serves the picture to signed-in users only', function () {
    Storage::fake('local');
    $agent = User::factory()->create();
    $this->actingAs($agent)->post(route('avatar.update'), ['avatar' => UploadedFile::fake()->image('me.jpg', 200, 200)]);
    $agent->refresh();

    $this->actingAs($agent)->get(route('profile.edit'))->assertInertia(fn (Assert $page) => $page
        ->where('auth.user.avatar', $agent->avatar)
        ->missing('auth.user.avatar_path'));
    $this->actingAs(User::factory()->create())->get($agent->avatar)->assertOk();

    auth()->logout();
    $this->get($agent->avatar)->assertRedirect(route('login'));
});

it('returns not found for a user without a profile picture', function () {
    $agent = User::factory()->create();

    $this->actingAs($agent)->get(route('avatars.show', $agent))->assertNotFound();
});
