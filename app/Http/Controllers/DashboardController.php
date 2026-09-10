<?php

namespace App\Http\Controllers;

use App\Http\Requests\DashboardRequest;
use App\Services\DashboardReport;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(DashboardRequest $request, DashboardReport $report): Response
    {
        return Inertia::render('dashboard', $report->for($request->user(), $request->validated()));
    }
}
