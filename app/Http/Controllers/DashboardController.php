<?php

namespace App\Http\Controllers;

use App\Models\EmailReply;
use App\Models\Lead;
use App\Models\UploadBatch;
use App\Models\User;
use App\UserRole;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $all = $user->canViewAllLeads();
        [$from, $to] = match ($request->string('period')->toString()) {
            'today' => [today(), now()],
            'week' => [now()->startOfWeek(), now()],
            'custom' => [$request->string('date_from')->toString() ?: null, $request->string('date_to')->toString() ?: null],
            default => [now()->startOfMonth(), now()],
        };
        $leads = Lead::query()->when(! $all, fn ($q) => $q->whereBelongsTo($user, 'agent'))->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to));
        $batches = UploadBatch::query()->when(! $all, fn ($q) => $q->whereBelongsTo($user))->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to));
        $totalLeads = (clone $leads)->count();
        $qualified = (clone $leads)->where('status', 'qualified_lead')->count();
        $replies = EmailReply::query()->when(! $all, fn ($query) => $query->whereBelongsTo($user, 'agent'));
        $stats = [
            'total_leads' => $totalLeads,
            'unique_leads' => (int) (clone $batches)->sum('new_leads'),
            'qualified_leads' => $qualified,
            'qualification_rate' => $totalLeads > 0 ? round(($qualified / $totalLeads) * 100, 1) : 0,
            'duplicates_flagged' => (int) (clone $batches)->sum('duplicate_rows'),
            'data_issues' => (int) (clone $batches)->sum('invalid_rows') + (int) (clone $batches)->sum('location_error_rows') + (int) (clone $batches)->sum('error_rows'),
            'unread_replies' => (clone $replies)->where('is_read', false)->count(),
            'possible_reply_leads' => (clone $replies)->whereIn('classification', ['interested', 'possible_lead'])->count(),
        ];

        $productivity = $user->isAdministrator() ? User::query()->where('role', UserRole::Agent)->withSum(['uploadBatches as uploaded' => fn ($query) => $query->when($from, fn ($query) => $query->whereDate('created_at', '>=', $from))->when($to, fn ($query) => $query->whereDate('created_at', '<=', $to))], 'total_rows')->withSum(['uploadBatches as unique_leads' => fn ($query) => $query->when($from, fn ($query) => $query->whereDate('created_at', '>=', $from))->when($to, fn ($query) => $query->whereDate('created_at', '<=', $to))], 'new_leads')->withSum(['uploadBatches as duplicates' => fn ($query) => $query->when($from, fn ($query) => $query->whereDate('created_at', '>=', $from))->when($to, fn ($query) => $query->whereDate('created_at', '<=', $to))], 'duplicate_rows')->withSum(['uploadBatches as errors' => fn ($query) => $query->when($from, fn ($query) => $query->whereDate('created_at', '>=', $from))->when($to, fn ($query) => $query->whereDate('created_at', '<=', $to))], 'rejected_rows')->withCount(['leads as possible' => fn ($query) => $query->where('status', 'possible_lead'), 'leads as qualified' => fn ($query) => $query->where('status', 'qualified_lead'), 'leads as forwarded' => fn ($query) => $query->where('status', 'forwarded')])->orderByDesc('uploaded')->get(['id', 'name']) : [];

        return Inertia::render('dashboard', [
            'stats' => $stats,
            'period' => $request->string('period')->toString() ?: 'month',
            'filters' => $request->only(['date_from', 'date_to']),
            'productivity' => $productivity,
            'recentBatches' => (clone $batches)
                ->with(['user' => fn ($query) => $query->withTrashed()->select(['id', 'name'])])
                ->latest()
                ->limit(5)
                ->get(),
            'recentLeads' => (clone $leads)
                ->with(['agent' => fn ($query) => $query->withTrashed()->select(['id', 'name'])])
                ->latest()
                ->limit(5)
                ->get(['id', 'lead_code', 'agent_id', 'company_name', 'status', 'created_at']),
        ]);
    }
}
