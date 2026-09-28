<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnalyticsRequest;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AnalyticsPdfReport;
use App\Services\AnalyticsReport;
use App\Services\CsvCellSanitizer;
use App\Services\DatabaseIntelligenceReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDocument;
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
            foreach ($data['databaseSections'] as $title => $rows) {
                $writeSection($title, $rows);
            }
            $writeSection('Summary', [
                ['Metric', 'Value'],
                ['Leads created', $data['summary']['total_leads']],
                ['Qualified leads', $data['summary']['qualified_leads']],
                ['Qualification rate', $data['summary']['qualification_rate'].'%'],
                ['Duplicates flagged', $data['summary']['duplicates']],
            ]);
            $writeDistribution('Lead status', $data['leadStatuses']);
            $writeDistribution('Lead sources', $data['sources']);
            $writeDistribution('Top countries', $data['countries']);

            if ($data['agentPerformance'] !== []) {
                $writeSection('Agent performance', [
                    ['Agent', 'Leads', 'Qualified', 'Qualification rate', 'Uploads', 'Avg batch size', 'Duplicate rate', 'Error rate'],
                    ...array_map(fn (array $agent): array => [$agent['name'], $agent['leads'], $agent['qualified'], $agent['qualification_rate'].'%', $agent['uploads'], $agent['avg_batch_size'], $agent['duplicate_rate'].'%', $agent['error_rate'].'%'], $data['agentPerformance']),
                ]);
            }

            fclose($stream);
        }, "Analytics-Report-{$data['filters']['date_from']}-to-{$data['filters']['date_to']}.csv", ['Content-Type' => 'text/csv']);
    }

    public function exportPdf(AnalyticsRequest $request, AnalyticsReport $analytics, AnalyticsPdfReport $pdfReport): HttpResponse
    {
        [$user, $data] = $this->reportData($request, $analytics);
        $this->logExport($request, $user, 'analytics.exported_pdf', 'Downloaded an analytics PDF report.', $data['filters']);

        $pdf = Pdf::loadView('reports.analytics', [
            'data' => $data,
            'pdf' => $pdfReport->for($data),
            'scope' => $user->canViewAllLeads() ? 'All records' : 'Your records only',
            'generatedBy' => $user->name,
        ])->setPaper('a4');
        $this->stampPageNumbers($pdf);

        return $pdf->download("Analytics-Report-{$data['filters']['date_from']}-to-{$data['filters']['date_to']}.pdf");
    }

    /**
     * Writes "Page X of Y" in the footer's right corner once the total is known,
     * which CSS page counters in Dompdf cannot provide.
     */
    private function stampPageNumbers(PdfDocument $pdf): void
    {
        $pdf->render();
        $dompdf = $pdf->getDomPDF();
        $canvas = $dompdf->getCanvas();
        $font = $dompdf->getFontMetrics()->getFont('Helvetica');
        $label = 'Page {PAGE_NUM} of {PAGE_COUNT}';
        $width = $dompdf->getFontMetrics()->getTextWidth('Page 00 of 00', $font, 6);

        $canvas->page_text($canvas->get_width() - 28.5 - $width, $canvas->get_height() - 27.5, $label, $font, 6, [0.58, 0.64, 0.72]);
    }

    /** @return array{0: User, 1: array<string, mixed>} */
    private function reportData(AnalyticsRequest $request, AnalyticsReport $analytics): array
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $data = $analytics->for($user, $request->validated());
        $data['databaseSections'] = app(DatabaseIntelligenceReport::class)->exportSections($data['databaseReport']);

        return [$user, $data];
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
