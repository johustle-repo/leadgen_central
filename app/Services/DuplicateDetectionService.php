<?php

namespace App\Services;

use App\Models\Lead;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class DuplicateDetectionService
{
    /** @param array<string, mixed> $data
     * @return array{lead: Lead, type: string, score: int, fields: list<string>}|null
     */
    public function find(array $data, ?int $exceptLeadId = null): ?array
    {
        $query = Lead::query()->when($exceptLeadId, fn (Builder $query) => $query->whereKeyNot($exceptLeadId));
        $email = filled($data['email'] ?? null) ? Str::lower(trim((string) $data['email'])) : null;
        $contactName = filled($data['contact_person'] ?? null) ? Str::squish((string) $data['contact_person']) : null;

        if (! $email && ! $contactName) {
            return null;
        }

        $exact = $email
            ? (clone $query)->where('email', $email)->oldest('id')->first()
            : (clone $query)->whereNull('email')->whereRaw('LOWER(contact_person) = ?', [Str::lower($contactName)])->oldest('id')->first();

        if (! $exact) {
            return null;
        }

        $sameName = $contactName && Str::lower(Str::squish((string) $exact->contact_person)) === Str::lower($contactName);
        $fields = array_values(array_filter([$email ? 'email' : null, $sameName ? 'contact_name' : null]));

        return ['lead' => $exact, 'type' => 'exact', 'score' => 100, 'fields' => $fields];
    }
}
