<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkDeleteUploadBatchesRequest;
use App\Http\Requests\ConfirmUploadMappingRequest;
use App\Http\Requests\StoreUploadBatchRequest;
use App\Jobs\ProcessUploadBatch;
use App\Models\AuditLog;
use App\Models\SystemSetting;
use App\Models\UploadBatch;
use App\Models\User;
use App\Services\CsvCellSanitizer;
use App\Services\CsvHeaderMapper;
use App\Services\UploadBatchCreator;
use App\Services\UploadBatchDeletion;
use App\Services\UploadBatchReanalyzer;
use App\UploadBatchStatus;
use App\UploadRowStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UploadBatchController extends Controller
{
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
        if ($column === 'agent') {
            $query->leftJoin('users as sort_agents', 'sort_agents.id', '=', 'upload_batches.user_id')
                ->orderBy('sort_agents.name', $direction);
        } else {
            $query->orderBy("upload_batches.{$column}", $direction);
        }
        $query->orderByDesc('upload_batches.id');

        $deletableTotal = $request->user()->isAdministrator()
            ? UploadBatch::query()->whereIn('processing_status', [UploadBatchStatus::Completed, UploadBatchStatus::Failed])->count()
            : 0;

        $requestedPerPage = $request->integer('per_page', 10);
        $perPage = in_array($requestedPerPage, [10, 25, 50, 100], true) ? $requestedPerPage : 10;

        return Inertia::render('uploads/index', [
            'batches' => $query->paginate($perPage)->withQueryString(),
            'sort' => $sort,
            'filters' => ['agent_id' => $request->string('agent_id')->toString(), 'per_page' => (string) $perPage],
            'deletableTotal' => $deletableTotal,
            'agents' => $request->user()->canViewAllLeads() ? User::query()->orderBy('name')->get(['id', 'name']) : [],
        ]);
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
        $headers = $uploadBatch->headers ?? [];
        $mapping = [];
        foreach ($request->validated('mapping') as $index => $field) {
            if (($header = $headers[(int) $index] ?? null) !== null) {
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
        $rowCount = $reanalyzer->prepare($uploadBatch);

        if ($rowCount === 0) {
            return back()->with('toast', ['type' => 'info', 'message' => 'This upload has no duplicate rows to re-analyze.']);
        }

        ProcessUploadBatch::dispatch($uploadBatch->id);

        return back()->with('toast', ['type' => 'success', 'message' => "{$rowCount} duplicate rows queued for re-analysis."]);
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
