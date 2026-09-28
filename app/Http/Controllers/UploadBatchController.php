<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkDeleteUploadBatchesRequest;
use App\Http\Requests\ConfirmUploadMappingRequest;
use App\Http\Requests\StoreUploadBatchRequest;
use App\Jobs\ProcessUploadBatch;
use App\Models\AuditLog;
use App\Models\SystemSetting;
use App\Models\UploadBatch;
use App\Models\UploadRow;
use App\Models\User;
use App\Services\CsvCellSanitizer;
use App\Services\CsvHeaderMapper;
use App\Services\UploadBatchCreator;
use App\Services\UploadBatchDeletion;
use App\Services\UploadBatchReanalyzer;
use App\Support\LeadReportDimensions;
use App\UploadBatchStatus;
use App\UploadRowStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UploadBatchController extends Controller
{
    /** Upload History status tabs and the batch statuses each one covers. */
    private const STATUS_TABS = [
        'all' => ['pending', 'processing', 'completed', 'failed'],
        'in_progress' => ['pending', 'processing'],
        'completed' => ['completed'],
        'failed' => ['failed'],
    ];

    /** Row outcomes a Lead Reports quality figure can open, as in DatabaseIntelligenceReport. */
    public const ROW_OUTCOMES = ['accepted', 'needs_review', 'duplicates', 'rejected', 'errors', 'location_issues'];

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', UploadBatch::class);
        $sort = $request->string('sort')->toString();
        $sortOptions = [
            'oldest' => ['created_at', 'asc'],
            'filename_asc' => ['original_filename', 'asc'],
            'filename_desc' => ['original_filename', 'desc'],
            'status' => ['processing_status', 'asc'],
            'agent_asc' => ['agent', 'asc'],
            'agent_desc' => ['agent', 'desc'],
            'newest' => ['created_at', 'desc'],
        ];
        $sort = array_key_exists($sort, $sortOptions) ? $sort : 'newest';
        [$column, $direction] = $sortOptions[$sort];
        $query = UploadBatch::query()->select('upload_batches.*')->with('user:id,name');
        if (! $request->user()->canViewAllLeads()) {
            $query->whereBelongsTo($request->user());
        } elseif ($agentId = $request->string('agent_id')->toString()) {
            $query->where('upload_batches.user_id', $agentId);
        }
        $search = $request->string('search')->trim()->limit(100, '')->toString();
        if ($search !== '') {
            $query->where(fn (Builder $builder) => $builder->where('upload_batches.original_filename', 'like', "%{$search}%")
                ->orWhere('upload_batches.batch_code', 'like', "%{$search}%"));
        }
        $request->validate([
            'created_from' => ['nullable', 'date_format:Y-m-d'],
            'created_to' => ['nullable', 'date_format:Y-m-d'],
            'row_outcome' => ['nullable', Rule::in(self::ROW_OUTCOMES)],
            'row_source' => ['nullable', Rule::in(LeadReportDimensions::SOURCE_GROUPS)],
        ]);
        $reportFilters = array_filter($request->only(['created_from', 'created_to', 'row_outcome', 'row_source']), fn (mixed $value): bool => is_string($value) && $value !== '');
        if (isset($reportFilters['created_from'])) {
            $query->where('upload_batches.created_at', '>=', $reportFilters['created_from'].' 00:00:00');
        }
        if (isset($reportFilters['created_to'])) {
            $query->where('upload_batches.created_at', '<=', $reportFilters['created_to'].' 23:59:59');
        }
        if (isset($reportFilters['row_outcome'])) {
            $query->whereIn('upload_batches.id', $this->rowsWithOutcome($reportFilters['row_outcome'])->select('upload_batch_id'));
        }
        if (isset($reportFilters['row_source'])) {
            // Same source rule as Lead Reports' source quality table, where a
            // row with no source of its own takes its batch's main source: a
            // batch is Unknown only when none of its rows has a known source.
            $rowSource = LeadReportDimensions::importRowSourceExpression();
            $rows = UploadRow::query()->leftJoin('leads as row_leads', 'row_leads.id', '=', 'upload_rows.lead_id')->select('upload_rows.upload_batch_id');
            if ($reportFilters['row_source'] === 'Unknown') {
                $query->whereIn('upload_batches.id', UploadRow::query()->select('upload_batch_id'))
                    ->whereNotIn('upload_batches.id', $rows->whereRaw("{$rowSource} != ?", ['Unknown']));
            } else {
                $query->whereIn('upload_batches.id', $rows->whereRaw("{$rowSource} = ?", [$reportFilters['row_source']]));
            }
        }
        // Summary figures describe everything matching the owner, agent and
        // search filters; the status tab only narrows the list below them.
        // select() replaces the list's upload_batches.* columns: MySQL rejects
        // plain columns mixed with aggregates when there is no GROUP BY.
        $summaryQuery = (clone $query)->toBase()->reorder();
        $summary = fn (): object => (clone $summaryQuery)->select(DB::raw('COUNT(*) as uploads, COALESCE(SUM(total_rows), 0) as rows_total, COALESCE(SUM(accepted_rows), 0) as accepted, COALESCE(SUM(duplicate_rows), 0) as duplicates, COALESCE(SUM(rejected_rows), 0) as rejected, COALESCE(SUM(error_rows), 0) as errors'))->first();
        $statusCounts = fn (): Collection => (clone $summaryQuery)->select('processing_status', DB::raw('COUNT(*) as aggregate'))->groupBy('processing_status')->pluck('aggregate', 'processing_status');
        $status = $request->string('status')->toString();
        $status = array_key_exists($status, self::STATUS_TABS) ? $status : 'all';
        if ($status !== 'all') {
            $query->whereIn('upload_batches.processing_status', self::STATUS_TABS[$status]);
        }
        if ($column === 'agent') {
            $query->leftJoin('users as sort_agents', 'sort_agents.id', '=', 'upload_batches.user_id')
                ->orderBy('sort_agents.name', $direction);
        } else {
            $query->orderBy("upload_batches.{$column}", $direction);
        }
        $query->orderByDesc('upload_batches.id');

        $deletableTotal = fn (): int => $request->user()->isAdministrator()
            ? UploadBatch::query()->whereIn('processing_status', [UploadBatchStatus::Completed, UploadBatchStatus::Failed])->count()
            : 0;

        $requestedPerPage = $request->integer('per_page', 10);
        $perPage = in_array($requestedPerPage, [10, 25, 50, 100], true) ? $requestedPerPage : 10;

        return Inertia::render('uploads/index', [
            'batches' => $query->paginate($perPage)->withQueryString(),
            'sort' => $sort,
            'filters' => ['agent_id' => $request->string('agent_id')->toString(), 'per_page' => (string) $perPage, 'status' => $status, 'search' => $search, ...$reportFilters],
            // Lazy: the page's progress poll reloads only 'batches'.
            'summary' => function () use ($summary): array {
                $totals = $summary();

                return [
                    'uploads' => (int) $totals->uploads,
                    'rows' => (int) $totals->rows_total,
                    'accepted' => (int) $totals->accepted,
                    'duplicates' => (int) $totals->duplicates,
                    'rejected' => (int) $totals->rejected,
                    'errors' => (int) $totals->errors,
                ];
            },
            'statusCounts' => function () use ($statusCounts): array {
                $counts = $statusCounts();

                return collect(self::STATUS_TABS)->map(fn (array $statuses): int => collect($statuses)->sum(fn (string $value): int => (int) ($counts[$value] ?? 0)))->all();
            },
            'deletableTotal' => $deletableTotal,
            'agents' => fn () => $request->user()->canViewAllLeads() ? User::query()->orderBy('name')->get(['id', 'name']) : [],
        ]);
    }

    /**
     * Rows counted by one Lead Reports quality outcome. Accepted includes
     * rows needing review, and duplicates include accepted rows flagged as
     * possible duplicates, matching the report's overlapping categories.
     *
     * @return Builder<UploadRow>
     */
    private function rowsWithOutcome(string $outcome): Builder
    {
        $rows = UploadRow::query();

        return match ($outcome) {
            'accepted' => $rows->whereIn('processing_status', [UploadRowStatus::Accepted, UploadRowStatus::NeedsReview]),
            'needs_review' => $rows->where('processing_status', UploadRowStatus::NeedsReview),
            'duplicates' => $rows->where('processing_status', '!=', UploadRowStatus::Pending)->where(fn (Builder $query) => $query->where('processing_status', UploadRowStatus::Duplicate)->orWhere('error_category', 'possible_duplicate')),
            'rejected' => $rows->where('processing_status', UploadRowStatus::Rejected),
            'errors' => $rows->where('processing_status', UploadRowStatus::Error),
            'location_issues' => $rows->where('processing_status', '!=', UploadRowStatus::Pending)->where('error_category', 'location'),
            default => throw new InvalidArgumentException("Unknown upload row outcome [{$outcome}]."),
        };
    }

    public function create(): Response
    {
        Gate::authorize('create', UploadBatch::class);

        return Inertia::render('uploads/create', [
            'maximumFiles' => (int) (SystemSetting::where('key', 'csv_max_files')->value('value') ?? config('leadgen.csv_max_files', 50)),
        ]);
    }

    public function store(StoreUploadBatchRequest $request, UploadBatchCreator $creator): RedirectResponse
    {
        $files = $request->file('files');
        $files = is_array($files) ? $files : [$request->file('file')];
        $files = array_values(array_filter($files));

        if (count($files) > 1) {
            $batches = $creator->createAndQueueMany($files, $request->user(), $request->validated('duplicate_handling'));

            return redirect()->route('uploads.index')->with('toast', ['type' => 'success', 'message' => "{$batches->count()} raw files uploaded and queued for cleaning."]);
        }

        $batch = $creator->createForMapping($files[0], $request->user(), $request->validated('duplicate_handling'));

        return redirect()->route('uploads.mapping', $batch);
    }

    public function mapping(UploadBatch $uploadBatch, CsvHeaderMapper $mapper): Response
    {
        Gate::authorize('update', $uploadBatch);

        return Inertia::render('uploads/mapping', ['batch' => $uploadBatch, 'fields' => $mapper->fields()]);
    }

    public function process(ConfirmUploadMappingRequest $request, UploadBatch $uploadBatch): RedirectResponse
    {
        // The mapping form submits fields keyed by column index (mapping[0], mapping[1], ...)
        // rather than by header text, because a header of "" serializes as mapping[] over
        // HTML forms, which PHP parses as an auto-incrementing array index instead of an
        // empty-string key - silently detaching that column from its selection. Headers are
        // guaranteed unique at upload time, so reconstructing a header-keyed map here is safe.
        // Blank headers are skipped rather than keyed as "": a padded export can carry many of
        // them, and they would otherwise all overwrite one another under that single key.
        $headers = $uploadBatch->headers ?? [];
        $mapping = [];
        foreach ($request->validated('mapping') as $index => $field) {
            $header = $headers[(int) $index] ?? null;
            if ($header !== null && trim((string) $header) !== '') {
                $mapping[$header] = $field;
            }
        }

        if (! in_array('company_name', array_values(array_filter($mapping)), true)) {
            throw ValidationException::withMessages(['mapping' => 'Map one CSV column to Company Name.']);
        }
        $uploadBatch->update(['column_mapping' => $mapping]);
        ProcessUploadBatch::dispatch($uploadBatch->id);

        return redirect()->route('uploads.show', $uploadBatch)->with('toast', ['type' => 'success', 'message' => 'CSV queued for processing.']);
    }

    public function reanalyze(UploadBatch $uploadBatch, UploadBatchReanalyzer $reanalyzer): RedirectResponse
    {
        Gate::authorize('reanalyze', $uploadBatch);
        $wasFailed = $uploadBatch->processing_status === UploadBatchStatus::Failed;
        $rowCount = $reanalyzer->prepare($uploadBatch);

        // A Completed batch with nothing to reset genuinely has no work to do. A
        // Failed batch, though, is requeued regardless - it never finished, so it
        // needs a full pass even when it has no rejected/duplicate rows of its own.
        if ($rowCount === 0 && ! $wasFailed) {
            return back()->with('toast', ['type' => 'info', 'message' => 'This upload has no duplicate, capped, or rejected rows to re-analyze.']);
        }

        ProcessUploadBatch::dispatch($uploadBatch->id);

        return back()->with('toast', ['type' => 'success', 'message' => $rowCount > 0 ? "{$rowCount} rows queued for re-analysis." : 'Upload queued for re-analysis.']);
    }

    public function reanalyzeAll(Request $request, UploadBatchReanalyzer $reanalyzer): RedirectResponse
    {
        Gate::authorize('reanalyzeAll', UploadBatch::class);

        $batches = UploadBatch::query()->whereIn('processing_status', [UploadBatchStatus::Completed, UploadBatchStatus::Failed])->get();
        $queuedBatches = 0;
        $queuedRows = 0;
        foreach ($batches as $batch) {
            $wasFailed = $batch->processing_status === UploadBatchStatus::Failed;
            $rowCount = $reanalyzer->prepare($batch);
            if ($rowCount === 0 && ! $wasFailed) {
                continue;
            }
            ProcessUploadBatch::dispatch($batch->id);
            $queuedBatches++;
            $queuedRows += $rowCount;
        }

        return back()->with('toast', $queuedBatches > 0
            ? ['type' => 'success', 'message' => "{$queuedBatches} uploads ({$queuedRows} rows) queued for re-analysis."]
            : ['type' => 'info', 'message' => 'No uploads have duplicate, capped, rejected, or unmatched-location rows to re-analyze.']);
    }

    public function retry(UploadBatch $uploadBatch): RedirectResponse
    {
        Gate::authorize('retry', $uploadBatch);
        ProcessUploadBatch::dispatch($uploadBatch->id);

        return back()->with('toast', ['type' => 'success', 'message' => 'Upload queued for processing again.']);
    }

    public function show(Request $request, UploadBatch $uploadBatch): Response
    {
        Gate::authorize('view', $uploadBatch);
        $status = $request->string('status')->toString();
        $rows = $uploadBatch->rows()->with('lead:id,lead_code,company_name')->when($status, fn ($q) => $q->where('processing_status', $status))->orderBy('row_number')->paginate(25)->withQueryString();

        return Inertia::render('uploads/show', ['batch' => $uploadBatch->load('user:id,name'), 'rows' => $rows, 'filter' => $status]);
    }

    public function errors(Request $request, UploadBatch $uploadBatch, CsvCellSanitizer $csv): StreamedResponse
    {
        Gate::authorize('view', $uploadBatch);

        $this->recordExport($request, $uploadBatch, 'problems');

        return response()->streamDownload(function () use ($uploadBatch, $csv): void {
            $stream = fopen('php://output', 'wb');
            if (! is_resource($stream)) {
                return;
            }
            $headers = array_map('strval', $uploadBatch->headers ?? []);
            fputcsv($stream, ['Row Number', 'Error Category', 'Error Message', ...$headers], escape: '');
            foreach ($uploadBatch->rows()->whereNotNull('error_category')->orderBy('row_number')->cursor() as $row) {
                fputcsv($stream, $csv->sanitizeRow([$row->row_number, $row->error_category, $row->error_message, ...array_map(fn (string $header): mixed => $row->raw_data[$header] ?? null, $headers)]), escape: '');
            }
            fclose($stream);
        }, $uploadBatch->batch_code.'-problems.csv', ['Content-Type' => 'text/csv']);
    }

    public function cleaned(Request $request, UploadBatch $uploadBatch, CsvCellSanitizer $csv): StreamedResponse
    {
        Gate::authorize('view', $uploadBatch);

        $this->recordExport($request, $uploadBatch, 'cleaned');

        return response()->streamDownload(function () use ($uploadBatch, $csv): void {
            $stream = fopen('php://output', 'wb');
            if (! is_resource($stream)) {
                return;
            }

            fputcsv($stream, ['Date', 'Company', 'Website', 'First Name', 'Email', 'Country', 'City', 'Import Trades', 'LinkedIn', 'Sources of Data', 'Link'], escape: '');
            foreach ($uploadBatch->rows()->where('processing_status', UploadRowStatus::Accepted)->whereNotNull('lead_id')->with('lead')->orderBy('row_number')->cursor() as $row) {
                $lead = $row->lead;
                if ($lead === null) {
                    continue;
                }

                fputcsv($stream, $csv->sanitizeRow([$lead->lead_date?->format('m/d/Y'), $lead->company_name, $lead->website, $lead->contact_person, $lead->email, $lead->country_code ?: $lead->country, $lead->city, $lead->import_trades, $lead->linkedin_url, $lead->data_source, $lead->source_url]), escape: '');
            }
            fclose($stream);
        }, $uploadBatch->batch_code.'-cleaned.csv', ['Content-Type' => 'text/csv']);
    }

    public function destroy(Request $request, UploadBatch $uploadBatch, UploadBatchDeletion $deletion): RedirectResponse
    {
        Gate::authorize('delete', $uploadBatch);
        $deletion->delete($uploadBatch, $request->user(), $request->ip(), $request->userAgent());

        return redirect()->route('uploads.index')->with('toast', ['type' => 'success', 'message' => 'Upload history deleted. Imported leads were preserved.']);
    }

    public function bulkDestroy(BulkDeleteUploadBatchesRequest $request, UploadBatchDeletion $deletion): RedirectResponse
    {
        $batches = $request->boolean('select_all')
            ? UploadBatch::query()->whereIn('processing_status', [UploadBatchStatus::Completed, UploadBatchStatus::Failed])->get()
            : UploadBatch::query()->whereKey($request->validated('upload_batch_ids'))->get();
        $batches->each(fn (UploadBatch $batch) => Gate::authorize('delete', $batch));
        $batches->each(fn (UploadBatch $batch) => $deletion->delete($batch, $request->user(), $request->ip(), $request->userAgent()));

        return redirect()->route('uploads.index')->with('toast', [
            'type' => 'success',
            'message' => "{$batches->count()} upload histories deleted successfully.",
        ]);
    }

    private function recordExport(Request $request, UploadBatch $uploadBatch, string $type): void
    {
        AuditLog::query()->create([
            'user_id' => $request->user()->id,
            'action' => "upload_batch.{$type}_exported",
            'auditable_type' => 'upload_batch',
            'auditable_id' => $uploadBatch->id,
            'description' => "Downloaded the {$type} CSV for {$uploadBatch->batch_code}.",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
