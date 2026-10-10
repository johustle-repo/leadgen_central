<?php

namespace App\Http\Controllers;

use App\Jobs\CreateDatabaseBackup;
use App\Models\AuditLog;
use App\Services\DatabaseBackupStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DatabaseBackupController extends Controller
{
    public function index(DatabaseBackupStore $store): Response
    {
        Gate::authorize('manage-backups');

        return Inertia::render('backups/index', [
            'backups' => $store->all(),
            'pending' => Cache::has(CreateDatabaseBackup::PENDING_CACHE_KEY),
            'keepDays' => (int) config('leadgen.backup.keep_days'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manage-backups');

        if (Cache::add(CreateDatabaseBackup::PENDING_CACHE_KEY, true, now()->addHour())) {
            CreateDatabaseBackup::dispatch();
            $this->audit($request, 'backup.requested', 'Requested a database backup.');
        }

        return back()->with('toast', ['type' => 'success', 'message' => 'Backup started. It will appear in the list within a few minutes.']);
    }

    public function download(Request $request, DatabaseBackupStore $store, string $backup): StreamedResponse
    {
        Gate::authorize('manage-backups');
        abort_unless($store->exists($backup), 404);

        $this->audit($request, 'backup.downloaded', "Downloaded database backup {$backup}.", ['file' => $backup]);

        return $store->disk()->download($store->path($backup), $backup);
    }

    /**
     * @param  array<string, string>  $metadata
     */
    private function audit(Request $request, string $action, string $description, array $metadata = []): void
    {
        AuditLog::query()->create([
            'user_id' => $request->user()->id,
            'action' => $action,
            'auditable_type' => 'system',
            'description' => $description,
            'metadata' => $metadata ?: null,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
