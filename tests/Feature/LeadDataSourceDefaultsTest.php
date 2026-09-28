<?php

use App\Models\Lead;
use App\Models\UploadBatch;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('records a lead saved from the form with a Tendata link and no data source as Tendata', function () {
    $agent = User::factory()->create();

    $this->actingAs($agent)->post(route('leads.store'), [
        'company_name' => 'Acme Ventures',
        'contact_person' => 'Ada',
        'email' => 'hello@acme.test',
        'source_url' => 'https://bizr.tendata.cn/enterprise#/base-info?name=ACME',
    ])->assertSessionHasNoErrors();

    expect(Lead::firstOrFail()->data_source)->toBe('Tendata');
});

it('fills the data source from a Tendata or Lusha link only when none is recorded', function (?string $source, ?string $link, ?string $expected) {
    $lead = Lead::factory()->create(['source' => 'csv', 'data_source' => $source, 'source_url' => $link]);

    expect($lead->refresh()->data_source)->toBe($expected);
})->with([
    'blank source, tendata link' => [null, 'https://bizr.tendata.cn/enterprise', 'Tendata'],
    'placeholder source, tendata link' => ['N/A', 'https://www.tendata.com/company/1', 'Tendata'],
    'chosen source is kept' => ['Lusha', 'https://bizr.tendata.cn/enterprise', 'Lusha'],
    'blank source, lusha link' => [null, 'https://dashboard.lusha.com/contacts/123', 'Lusha'],
    'placeholder source, lusha link' => ['n/a', 'https://www.lusha.com/company/acme', 'Lusha'],
    'chosen source kept over lusha link' => ['Tendata', 'https://dashboard.lusha.com/contacts/123', 'Tendata'],
    'other link' => [null, 'https://example.com/tendata-review', null],
    'lusha only in the path' => [null, 'https://example.com/lusha-export', null],
]);

it('leaves a missing LinkedIn blank instead of a placeholder', function (?string $linkedin, ?string $expected) {
    $lead = Lead::factory()->create(['linkedin_url' => $linkedin]);

    expect($lead->refresh()->linkedin_url)->toBe($expected);
})->with([
    'N/A' => ['N/A', null],
    'lowercase n/a' => [' n/a ', null],
    'empty' => ['', null],
    'real profile' => ['https://www.linkedin.com/in/john-austin-27587bb5', 'https://www.linkedin.com/in/john-austin-27587bb5'],
]);

it('records a lead saved from the form with a Lusha link and no data source as Lusha', function () {
    $agent = User::factory()->create();

    $this->actingAs($agent)->post(route('leads.store'), [
        'company_name' => 'Acme Ventures',
        'contact_person' => 'Ada',
        'email' => 'hello@acme.test',
        'source_url' => 'https://dashboard.lusha.com/contacts/123',
    ])->assertSessionHasNoErrors();

    expect(Lead::firstOrFail()->data_source)->toBe('Lusha');
});

it('backfills existing leads with a Lusha link as Lusha', function () {
    $lusha = Lead::factory()->create();
    $chosen = Lead::factory()->create();
    Lead::query()->whereKey($lusha->id)->update(['data_source' => null, 'source_url' => 'https://dashboard.lusha.com/contacts/123']);
    Lead::query()->whereKey($chosen->id)->update(['data_source' => 'Tendata', 'source_url' => 'https://dashboard.lusha.com/contacts/456']);

    (require database_path('migrations/2026_09_28_052931_backfill_lead_data_source_from_lusha_links.php'))->up();

    expect($lusha->refresh()->data_source)->toBe('Lusha')
        ->and($chosen->refresh()->data_source)->toBe('Tendata');
});

it('records a hand-entered lead with neither a data source nor a link as Manual', function () {
    $agent = User::factory()->create();

    $this->actingAs($agent)->post(route('leads.store'), [
        'company_name' => 'Acme Ventures',
        'contact_person' => 'Ada',
        'email' => 'hello@acme.test',
        'data_source' => '',
        'source_url' => '',
    ])->assertSessionHasNoErrors();

    expect(Lead::firstOrFail()->data_source)->toBe('Manual');
});

it('marks any lead without a source or link as Manual, imported or hand-entered', function (string $entry, ?string $source, ?string $link, ?string $expected) {
    $lead = Lead::factory()->create(['source' => $entry, 'data_source' => $source, 'source_url' => $link]);

    expect($lead->refresh()->data_source)->toBe($expected);
})->with([
    'hand-entered, no link' => ['manual', null, null, 'Manual'],
    'hand-entered, placeholder link' => ['manual', null, 'N/A', 'Manual'],
    'imported, no link' => ['csv', null, null, 'Manual'],
    'imported, N/A source and link' => ['csv', 'N/A', 'N/A', 'Manual'],
    'other link' => ['csv', null, 'https://example.com', null],
    'chosen source kept' => ['csv', 'Lusha', null, 'Lusha'],
]);

it('records an imported row with no source or link as Manual', function () {
    Storage::fake('local');
    $agent = User::factory()->create();
    $file = UploadedFile::fake()->createWithContent('blank-source.csv', "Company,Email,Sources of Data,Link\nAcme,ada@acme.test,,\n");
    $this->actingAs($agent)->post(route('uploads.store'), ['file' => $file]);
    $batch = UploadBatch::firstOrFail();

    $this->actingAs($agent)->post(route('uploads.process', $batch), ['mapping' => [
        0 => 'company_name', 1 => 'email', 2 => 'data_source', 3 => 'source_url',
    ]]);

    expect(Lead::firstOrFail()->data_source)->toBe('Manual');
});

it('backfills every lead without a source or link as Manual', function () {
    $imported = Lead::factory()->create(['source' => 'csv']);
    $linked = Lead::factory()->create(['source' => 'csv']);
    Lead::query()->whereKey($imported->id)->update(['data_source' => 'N/A', 'source_url' => 'N/A']);
    Lead::query()->whereKey($linked->id)->update(['data_source' => 'N/A', 'source_url' => 'https://example.com']);

    (require database_path('migrations/2026_09_28_054957_backfill_manual_data_source_for_leads_without_source_or_link.php'))->up();

    expect($imported->refresh()->data_source)->toBe('Manual')
        ->and($linked->refresh()->data_source)->toBe('N/A');
});

it('backfills hand-entered leads without a source or link as Manual', function () {
    $manual = Lead::factory()->create();
    $imported = Lead::factory()->create(['source' => 'csv']);
    Lead::query()->whereKey([$manual->id, $imported->id])->update(['data_source' => 'N/A', 'source_url' => null]);

    (require database_path('migrations/2026_09_28_054020_backfill_manual_data_source_for_hand_entered_leads.php'))->up();

    expect($manual->refresh()->data_source)->toBe('Manual')
        ->and($imported->refresh()->data_source)->toBe('N/A');
});

it('backfills existing leads saved before the rules applied', function () {
    $tendata = Lead::factory()->create();
    $chosen = Lead::factory()->create();
    Lead::query()->whereKey($tendata->id)->update(['data_source' => 'N/A', 'source_url' => 'https://bizr.tendata.cn/enterprise', 'linkedin_url' => 'N/A']);
    Lead::query()->whereKey($chosen->id)->update(['data_source' => 'Lusha', 'source_url' => 'https://bizr.tendata.cn/enterprise', 'linkedin_url' => 'https://linkedin.com/in/ada']);

    (require database_path('migrations/2026_09_28_051632_backfill_lead_data_source_and_linkedin_placeholders.php'))->up();

    expect($tendata->refresh()->only(['data_source', 'linkedin_url']))->toBe(['data_source' => 'Tendata', 'linkedin_url' => null])
        ->and($chosen->refresh()->only(['data_source', 'linkedin_url']))->toBe(['data_source' => 'Lusha', 'linkedin_url' => 'https://linkedin.com/in/ada']);
});
