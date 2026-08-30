<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): Response
    {
        Gate::authorize('viewAny', AuditLog::class);
        $logs = AuditLog::query()->with('user:id,name,email')->latest()->paginate(25)->withQueryString();

        return Inertia::render('audit-logs/index', ['logs' => $logs]);
    }
}
