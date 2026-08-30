<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkDeleteUploadBatchesRequest;
use App\Http\Requests\ConfirmUploadMappingRequest;
use App\Http\Requests\StoreUploadBatchRequest;
use App\Jobs\ProcessUploadBatch;
use App\Models\UploadBatch;
use App\Services\CsvHeaderMapper;
use App\Services\UploadBatchCreator;
use App\Services\UploadBatchDeletion;
use App\Services\UploadBatchReanalyzer;
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
        $query = UploadBatch::with('user:id,name')->latest();
        if (! $request->user()->canViewAllLeads()) {
            $query->whereBelongsTo($request->user());
        }

        return Inertia::render('uploads/index', ['batches' => $query->paginate(15)->withQueryString()]);
    }

    public function create(): Response
    {
        Gate::authorize('create', UploadBatch::class);

        return Inertia::render('uploads/create');
    }

    public function store(StoreUploadBatchRequest $request, UploadBatchCreator $creator): RedirectResponse
    {
        $files = $request->file('files');
        $files = is_array($files) ? $files : [$request->file('file')];
        $files = array_values(array_filter($files));

        if (count($files) > 1) {
            $batches = $creator->createAndQueueMany($files, $request->user());

            return redirect()->route('uploads.index')->with('toast', ['type' => 'success', 'message' => "{$batches->count()} raw files uploaded and queued for cleaning."]);
        }

        $batch = $creator->createForMapping($files[0], $request->user());

        return redirect()->route('uploads.mapping', $batch);
    }

    public function mapping(UploadBatch $uploadBatch, CsvHeaderMapper $mapper): Response
    {
        Gate::authorize('update', $uploadBatch);

        return Inertia::render('uploads/mapping', ['batch' => $uploadBatch, 'fields' => $mapper->fields()]);
    }

    public function process(ConfirmUploadMappingRequest $request, UploadBatch $uploadBatch): RedirectResponse
    {
        if (! in_array('company_name', array_values(array_filter($request->validated('mapping'))), true)) {
            throw ValidationException::withMessages(['mapping' => 'Map one CSV column to Company Name.']);
        }
        $uploadBatch->update(['column_mapping' => $request->validated('mapping')]);
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

    public function show(Request $request, UploadBatch $uploadBatch): Response
    {
        Gate::authorize('view', $uploadBatch);
        $status = $request->string('status')->toString();
        $rows = $uploadBatch->rows()->with('lead:id,lead_code,company_name')->when($status, fn ($q) => $q->where('processing_status', $status))->orderBy('row_number')->paginate(25)->withQueryString();

        return Inertia::render('uploads/show', ['batch' => $uploadBatch->load('user:id,name'), 'rows' => $rows, 'filter' => $status]);
    }

    public function errors(UploadBatch $uploadBatch): StreamedResponse
    {
        Gate::authorize('view', $uploadBatch);

        return response()->streamDownload(function () use ($uploadBatch): void {
            $stream = fopen('php://output', 'wb');
            if (! is_resource($stream)) {
                return;
            }
            $headers = array_map('strval', $uploadBatch->headers ?? []);
            fputcsv($stream, ['Row Number', 'Error Category', 'Error Message', ...$headers], escape: '');
            foreach ($uploadBatch->rows()->whereNotNull('error_category')->orderBy('row_number')->cursor() as $row) {
                fputcsv($stream, [$row->row_number, $row->error_category, $row->error_message, ...array_map(fn (string $header): mixed => $row->raw_data[$header] ?? null, $headers)], escape: '');
            }
            fclose($stream);
        }, $uploadBatch->batch_code.'-problems.csv', ['Content-Type' => 'text/csv']);
    }

    public function cleaned(UploadBatch $uploadBatch): StreamedResponse
    {
        Gate::authorize('view', $uploadBatch);

        return response()->streamDownload(function () use ($uploadBatch): void {
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

                fputcsv($stream, [$lead->lead_date?->format('m/d/Y'), $lead->company_name, $lead->website, $lead->contact_person, $lead->email, $lead->country_code ?: $lead->country, $lead->city, $lead->import_trades, $lead->linkedin_url, $lead->data_source, $lead->source_url], escape: '');
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
        $batches = UploadBatch::query()->whereKey($request->validated('upload_batch_ids'))->get();
        $batches->each(fn (UploadBatch $batch) => Gate::authorize('delete', $batch));
        $batches->each(fn (UploadBatch $batch) => $deletion->delete($batch, $request->user(), $request->ip(), $request->userAgent()));

        return redirect()->route('uploads.index')->with('toast', [
            'type' => 'success',
            'message' => "{$batches->count()} upload histories deleted successfully.",
        ]);
    }
}
