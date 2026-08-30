<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSystemSettingsRequest;
use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SystemSettingController extends Controller
{
    public function edit(): Response
    {
        Gate::authorize('manage-settings');

        return Inertia::render('system-settings/edit', ['settings' => ['csv_max_kilobytes' => (int) (SystemSetting::where('key', 'csv_max_kilobytes')->value('value') ?? config('leadgen.csv_max_kilobytes'))]]);
    }

    public function update(UpdateSystemSettingsRequest $request): RedirectResponse
    {
        SystemSetting::updateOrCreate(['key' => 'csv_max_kilobytes'], ['value' => (string) $request->integer('csv_max_kilobytes')]);

        return back()->with('toast', ['type' => 'success', 'message' => 'Settings updated.']);
    }
}
