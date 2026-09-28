<?php

use App\Models\Lead;
use App\Models\UploadBatch;
use App\Models\User;
use App\Services\DashboardReport;
use App\Support\ReportCache;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->travelTo('2026-09-28 12:00:00');
    $this->administrator = User::factory()->administrator()->create();
    $this->report = fn (): array => app(DashboardReport::class)->for($this->administrator, ['date_from' => '2026-09-01', 'date_to' => '2026-09-28'])['databaseAnalytics'];
});

it('shows an edited lead in the report straight away instead of the cached figures', function () {
    $lead = Lead::factory()->create(['industry' => 'Construction']);
    expect(collect(($this->report)()['distributions']['industries'])->pluck('value', 'label')->all())->toBe(['construction' => 1]);

    $this->actingAs($lead->agent)->put(route('leads.update', $lead), [...$lead->only(['company_name', 'contact_person', 'email']), 'industry' => 'Scaffolding'])
        ->assertSessionHasNoErrors();

    expect(collect(($this->report)()['distributions']['industries'])->pluck('value', 'label')->all())->toBe(['scaffolding' => 1]);
});

it('shows added and deleted leads in the report straight away', function () {
    Lead::factory()->create();
    expect(($this->report)()['overview']['records'])->toBe(1);

    $second = Lead::factory()->create();
    expect(($this->report)()['overview']['records'])->toBe(2);

    $second->delete();
    expect(($this->report)()['overview']['records'])->toBe(1);
});

it('keeps serving the cached report while nothing changes', function () {
    Lead::factory()->create();
    ($this->report)();

    DB::enableQueryLog();
    ($this->report)();

    expect(DB::getQueryLog())->toBeEmpty();
});

it('refreshes the report once a bulk write finishes, not for each record in it', function () {
    $keyBefore = ReportCache::key('dashboard-report', 'test', []);

    ReportCache::deferWhile(function () use ($keyBefore) {
        UploadBatch::factory()->create();
        Lead::factory()->count(3)->create();

        expect(ReportCache::key('dashboard-report', 'test', []))->toBe($keyBefore);
    });

    expect(ReportCache::key('dashboard-report', 'test', []))->not->toBe($keyBefore);
});

it('refreshes the report only when a transaction commits', function () {
    $keyBefore = ReportCache::key('dashboard-report', 'test', []);

    try {
        DB::transaction(function () {
            Lead::factory()->create();

            throw new RuntimeException('Rolled back.');
        });
    } catch (RuntimeException) {
    }
    expect(ReportCache::key('dashboard-report', 'test', []))->toBe($keyBefore);

    DB::transaction(fn () => Lead::factory()->create());
    expect(ReportCache::key('dashboard-report', 'test', []))->not->toBe($keyBefore);
});
