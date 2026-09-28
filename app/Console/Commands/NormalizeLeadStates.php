<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Services\LeadStateResolver;
use App\Support\ReportCache;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('leads:normalize-states {--dry-run : Show what would change without saving}')]
#[Description('Rewrite US and Canadian leads\' City to the full state/province name and list cities that could not be placed')]
class NormalizeLeadStates extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(LeadStateResolver $resolver): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $changed = 0;
        /** @var array<string, int> $changes */
        $changes = [];
        /** @var array<string, int> $unresolved */
        $unresolved = [];

        Lead::query()
            ->where(fn ($query) => $query->whereIn('country_code', LeadStateResolver::COUNTRIES)->orWhereNull('country_code')->orWhere('country_code', ''))
            ->chunkById(500, function ($leads) use ($resolver, $dryRun, &$changed, &$changes, &$unresolved): void {
                foreach ($leads as $lead) {
                    if (LeadStateResolver::countryOf($lead->country_code, $lead->country) === null) {
                        continue;
                    }
                    $before = (string) $lead->city;
                    if (! $resolver->normalize($lead)) {
                        $unresolved[$before === '' ? '(blank)' : $before] = ($unresolved[$before === '' ? '(blank)' : $before] ?? 0) + 1;

                        continue;
                    }
                    if (! $lead->isDirty()) {
                        continue;
                    }
                    $changed++;
                    $change = "{$before} → {$lead->city}";
                    $changes[$change] = ($changes[$change] ?? 0) + 1;
                    if (! $dryRun) {
                        $lead->saveQuietly();
                    }
                }
            });

        if (! $dryRun && $changed > 0) {
            ReportCache::flush();
        }

        arsort($changes);
        arsort($unresolved);
        $this->table(['Change', 'Leads'], collect($changes)->take(50)->map(fn (int $count, string $change): array => [$change, $count])->values()->all());
        $this->info(($dryRun ? 'Would update ' : 'Updated ').$changed.' US/Canadian leads.');

        if ($unresolved !== []) {
            $this->warn(count($unresolved).' city values could not be placed in a state; add them to leadgen.city_states or fix the leads:');
            $this->table(['City as entered', 'Leads'], collect($unresolved)->map(fn (int $count, string $city): array => [$city, $count])->values()->all());
        }

        return self::SUCCESS;
    }
}
