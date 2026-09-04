<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnalyticsRequest;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AnalyticsReport;
use App\Services\CsvCellSanitizer;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnalyticsController extends Controller
{
    public function __invoke(AnalyticsRequest $request, AnalyticsReport $analytics): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return Inertia::render('analytics/index', $analytics->for($user, $request->validated()));
    }

    public function export(AnalyticsRequest $request, AnalyticsReport $analytics, CsvCellSanitizer $csv): StreamedResponse
    {
        [$user, $data] = $this->reportData($request, $analytics);
        $this->logExport($request, $user, 'analytics.exported', 'Downloaded an analytics report export.', $data['filters']);

        return response()->streamDownload(function () use ($data, $csv): void {
            $stream = fopen('php://output', 'wb');
            if (! is_resource($stream)) {
                return;
            }

            $writeSection = function (string $title, array $rows) use ($stream, $csv): void {
                fputcsv($stream, [$title], escape: '');
                foreach ($rows as $row) {
                    fputcsv($stream, $csv->sanitizeRow($row), escape: '');
                }
                fputcsv($stream, [], escape: '');
            };
            $writeDistribution = function (string $title, array $items) use ($writeSection): void {
                $writeSection($title, [['Label', 'Count'], ...array_map(fn (array $item): array => [$item['label'], $item['value']], $items)]);
            };

            fputcsv($stream, ['Report period', "{$data['filters']['date_from']} to {$data['filters']['date_to']}"], escape: '');
            fputcsv($stream, [], escape: '');
            $writeSection('Summary', [
                ['Metric', 'Value'],
                ['Leads created', $data['summary']['total_leads']],
                ['Qualified leads', $data['summary']['qualified_leads']],
                ['Qualification rate', $data['summary']['qualification_rate'].'%'],
                ['Email replies', $data['summary']['replies']],
                ['Reply rate', $data['summary']['reply_rate'].'%'],
                ['Interested replies', $data['summary']['interested_replies']],
                ['Duplicates flagged', $data['summary']['duplicates']],
            ]);
            $writeDistribution('Lead status', $data['leadStatuses']);
            $writeDistribution('Reply classification', $data['replyClassifications']);
            $writeDistribution('Lead sources', $data['sources']);
            $writeDistribution('Top countries', $data['countries']);

            if ($data['agentPerformance'] !== []) {
                $writeSection('Agent performance', [
                    ['Agent', 'Leads', 'Qualified', 'Qualification rate', 'Replies', 'Interested', 'Uploads', 'Avg batch size', 'Duplicate rate', 'Error rate'],
                    ...array_map(fn (array $agent): array => [$agent['name'], $agent['leads'], $agent['qualified'], $agent['qualification_rate'].'%', $agent['replies'], $agent['interested'], $agent['uploads'], $agent['avg_batch_size'], $agent['duplicate_rate'].'%', $agent['error_rate'].'%'], $data['agentPerformance']),
                ]);
            }

            fclose($stream);
        }, "Analytics-Report-{$data['filters']['date_from']}-to-{$data['filters']['date_to']}.csv", ['Content-Type' => 'text/csv']);
    }

    public function exportPdf(AnalyticsRequest $request, AnalyticsReport $analytics): HttpResponse
    {
        [$user, $data] = $this->reportData($request, $analytics);
        $this->logExport($request, $user, 'analytics.exported_pdf', 'Downloaded an analytics PDF report.', $data['filters']);

        return Pdf::loadView('reports.analytics', ['data' => $data])
            ->download("Analytics-Report-{$data['filters']['date_from']}-to-{$data['filters']['date_to']}.pdf");
    }

    /** @return array{0: User, 1: array<string, mixed>} */
    private function reportData(AnalyticsRequest $request, AnalyticsReport $analytics): array
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return [$user, $analytics->for($user, $request->validated())];
    }

    /** @param  array<string, mixed>  $metadata */
    private function logExport(AnalyticsRequest $request, User $user, string $action, string $description, array $metadata): void
    {
        AuditLog::query()->create([
            'user_id' => $user->id,
            'action' => $action,
            'auditable_type' => 'lead',
            'description' => $description,
            'metadata' => $metadata,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
