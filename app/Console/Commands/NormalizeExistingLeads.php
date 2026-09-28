<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Services\LeadNormalizationService;
use App\Services\LeadStateResolver;
use App\Services\LocationMatchingService;
use App\Support\ReportCache;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('leads:normalize')]
#[Description('Backfill normalized domains, company names, canonical locations, and timezones')]
class NormalizeExistingLeads extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(LeadNormalizationService $normalizer, LocationMatchingService $locations, LeadStateResolver $states): int
    {
        $updated = 0;
        Lead::query()->chunkById(250, function ($leads) use ($normalizer, $locations, $states, &$updated): void {
            foreach ($leads as $lead) {
                $normalized = $normalizer->normalize($lead->only(['company_name', 'website', 'email', 'phone']));
                $location = $locations->match($lead->country_code ?? $lead->country, $lead->city);
                $lead->fill([...$normalized, ...$location->leadAttributes($lead->raw_city ?? $lead->city, $lead->raw_country ?? $lead->country_code ?? $lead->country)]);
                // leadAttributes() restores the City as entered; put US and
                // Canadian leads back on their state/province.
                $states->normalize($lead);
                $lead->saveQuietly();
                $updated++;
            }
        });
        ReportCache::flush();
        $this->info("Normalized {$updated} existing leads.");

        return self::SUCCESS;
    }
}
