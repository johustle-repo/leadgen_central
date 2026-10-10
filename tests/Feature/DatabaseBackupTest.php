<?php

use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * @return array{path: string, sql: string}
 */
function latestBackup(): array
{
    $path = collect(Storage::disk('local')->files('backups'))->sort()->last();

    return ['path' => $path, 'sql' => gzdecode(Storage::disk('local')->get($path))];
}

it('writes a backup that restores every table and row into an empty database', function () {
    Storage::fake('local');
    $user = User::factory()->create(['name' => "O'Brien Ünicode"]);

    $this->artisan('app:backup-database')->assertSuccessful();

    config(['database.connections.restore' => ['driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true]]);
    DB::connection('restore')->unprepared(latestBackup()['sql']);

    expect(DB::connection('restore')->table('users')->where('id', $user->id)->value('name'))->toBe("O'Brien Ünicode")
        ->and(DB::connection('restore')->table('users')->count())->toBe(User::count())
        ->and(DB::connection('restore')->table('migrations')->count())->toBe(DB::table('migrations')->count());
});

it('keeps the structure but not the rows of session and cache tables', function () {
    Storage::fake('local');
    DB::table('sessions')->insert(['id' => 'secret-session', 'payload' => 'session-payload', 'last_activity' => now()->timestamp]);

    $this->artisan('app:backup-database')->assertSuccessful();

    expect(latestBackup()['sql'])->toContain('CREATE TABLE "sessions"')
        ->not->toContain('secret-session');
});

it('removes backups older than the retention period and keeps recent ones', function () {
    Storage::fake('local');
    config(['leadgen.backup.keep_days' => 14]);
    $disk = Storage::disk('local');
    $disk->put('backups/leadgen-2026-01-01-020000.sql.gz', 'old');
    $disk->put('backups/leadgen-2026-01-20-020000.sql.gz', 'recent');
    $disk->put('backups/notes.txt', 'unrelated');
    touch($disk->path('backups/leadgen-2026-01-01-020000.sql.gz'), now()->subDays(15)->getTimestamp());
    touch($disk->path('backups/notes.txt'), now()->subDays(30)->getTimestamp());

    $this->artisan('app:backup-database')->assertSuccessful();

    expect($disk->exists('backups/leadgen-2026-01-01-020000.sql.gz'))->toBeFalse()
        ->and($disk->exists('backups/leadgen-2026-01-20-020000.sql.gz'))->toBeTrue()
        ->and($disk->exists('backups/notes.txt'))->toBeTrue()
        ->and($disk->files('backups'))->toHaveCount(3);
});

it('fails and removes nothing when the backup cannot be stored', function () {
    $disk = Mockery::mock(Filesystem::class);
    $disk->shouldReceive('writeStream')->once()->andReturnFalse();
    $disk->shouldNotReceive('delete');
    Storage::shouldReceive('disk')->andReturn($disk);

    $this->artisan('app:backup-database')
        ->expectsOutputToContain('Database backup failed')
        ->assertFailed();
});

it('schedules the database backup daily at 2 AM Philippine time', function () {
    $event = collect(app(Schedule::class)->events())->firstWhere('description', 'database-backup');

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 2 * * *')
        ->and($event->timezone)->toBe('Asia/Manila');
});
