<?php

namespace App\Http\Controllers;

use App\Http\Requests\DashboardRequest;
use App\Services\DashboardReport;
use App\Support\ReportCache;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(DashboardRequest $request, DashboardReport $report): Response
    {
        $version = ReportCache::version();

        return Inertia::render('dashboard', [...$report->for($request->user(), $request->validated()), 'reportVersion' => $version]);
    }
}
