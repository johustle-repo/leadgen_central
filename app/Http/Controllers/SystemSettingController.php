<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSystemSettingsRequest;
use App\Models\AuditLog;
use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SystemSettingController extends Controller
{
    public function edit(Request $request): Response
    {
        Gate::authorize('manage-settings');

        return Inertia::render('system-settings/edit', [
            'settings' => [
                'csv_max_kilobytes' => (int) (SystemSetting::where('key', 'csv_max_kilobytes')->value('value') ?? config('leadgen.csv_max_kilobytes')),
                'csv_max_files' => (int) (SystemSetting::where('key', 'csv_max_files')->value('value') ?? config('leadgen.csv_max_files')),
            ],
            'maintenance' => [
                'active' => app()->isDownForMaintenance(),
                'can_manage' => $request->user()->isSuperAdministrator(),
            ],
            'isSuperAdministrator' => $request->user()->isSuperAdministrator(),
        ]);
    }

    public function update(UpdateSystemSettingsRequest $request): RedirectResponse
    {
        SystemSetting::updateOrCreate(['key' => 'csv_max_kilobytes'], ['value' => (string) $request->integer('csv_max_kilobytes')]);
        SystemSetting::updateOrCreate(['key' => 'csv_max_files'], ['value' => (string) $request->integer('csv_max_files')]);

        return back()->with('toast', ['type' => 'success', 'message' => 'Settings updated.']);
    }

    public function enableMaintenance(Request $request): RedirectResponse
    {
        Gate::authorize('manage-maintenance');
        Artisan::call('down');
        AuditLog::query()->create([
            'user_id' => $request->user()->id,
            'action' => 'system.maintenance_enabled',
            'auditable_type' => 'system',
            'description' => 'Enabled maintenance mode. Visitors now see the maintenance page.',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => 'Maintenance mode enabled.']);
    }

    public function disableMaintenance(Request $request): RedirectResponse
    {
        Gate::authorize('manage-maintenance');
        Artisan::call('up');
        AuditLog::query()->create([
            'user_id' => $request->user()->id,
            'action' => 'system.maintenance_disabled',
            'auditable_type' => 'system',
            'description' => 'Disabled maintenance mode. The site is back online.',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => 'Maintenance mode disabled.']);
    }
}
