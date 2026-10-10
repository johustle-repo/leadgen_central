<?php

use App\Jobs\CreateDatabaseBackup;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

const BACKUP_FILE = 'leadgen-2026-10-10-020000.sql.gz';

it('lists stored backups to super administrators and administrators', function (string $role) {
    Storage::fake('local');
    Storage::disk('local')->put('backups/'.BACKUP_FILE, 'backup');
    Storage::disk('local')->put('backups/notes.txt', 'unrelated');

    $this->actingAs(User::factory()->create(['role' => $role]))->get(route('backups.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('backups/index')
            ->has('backups', 1)
            ->where('backups.0.name', BACKUP_FILE)
            ->where('backups.0.size', 6)
            ->where('pending', false));
})->with(['super_administrator', 'administrator']);

it('forbids other roles from viewing, requesting or downloading backups', function (string $role) {
    Storage::fake('local');
    Storage::disk('local')->put('backups/'.BACKUP_FILE, 'backup');
    $user = User::factory()->create(['role' => $role]);

    $this->actingAs($user)->get(route('backups.index'))->assertForbidden();
    $this->actingAs($user)->post(route('backups.store'))->assertForbidden();
    $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('backups.download', BACKUP_FILE))->assertForbidden();
})->with(['sub_administrator', 'agent']);

it('queues one backup at a time and records who requested it', function () {
    Queue::fake();
    $administrator = User::factory()->administrator()->create();

    $this->actingAs($administrator)->post(route('backups.store'))
        ->assertRedirect()->assertSessionHas('toast.type', 'success');
    $this->actingAs($administrator)->post(route('backups.store'))->assertRedirect();

    Queue::assertPushed(CreateDatabaseBackup::class, 1);
    expect(Cache::has(CreateDatabaseBackup::PENDING_CACHE_KEY))->toBeTrue();
    $this->assertDatabaseCount('audit_logs', 1);
    $this->assertDatabaseHas('audit_logs', ['user_id' => $administrator->id, 'action' => 'backup.requested']);
});

it('runs the backup command and clears the pending flag', function () {
    Cache::put(CreateDatabaseBackup::PENDING_CACHE_KEY, true);
    Artisan::shouldReceive('call')->once()->with('app:backup-database')->andReturn(0);

    (new CreateDatabaseBackup)->handle();

    expect(Cache::has(CreateDatabaseBackup::PENDING_CACHE_KEY))->toBeFalse();
});

it('requires password confirmation before downloading a backup', function () {
    Storage::fake('local');
    Storage::disk('local')->put('backups/'.BACKUP_FILE, 'backup');

    $this->actingAs(User::factory()->administrator()->create())
        ->get(route('backups.download', BACKUP_FILE))
        ->assertRedirect(route('password.confirm'));
});

it('downloads a backup and records it in the audit log', function () {
    Storage::fake('local');
    Storage::disk('local')->put('backups/'.BACKUP_FILE, 'backup');
    $superAdministrator = User::factory()->superAdministrator()->create();

    $response = $this->actingAs($superAdministrator)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('backups.download', BACKUP_FILE));

    $response->assertOk()->assertDownload(BACKUP_FILE);
    expect($response->streamedContent())->toBe('backup');
    $this->assertDatabaseHas('audit_logs', ['user_id' => $superAdministrator->id, 'action' => 'backup.downloaded']);
});

it('does not serve files outside the backup naming pattern', function (string $fileName) {
    Storage::fake('local');
    Storage::disk('local')->put('backups/notes.txt', 'unrelated');

    $this->actingAs(User::factory()->administrator()->create())
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get('/backups/'.$fileName)
        ->assertNotFound();
})->with(['notes.txt', '..%2F.env', 'leadgen-2026-10-11-020000.sql.gz']);
