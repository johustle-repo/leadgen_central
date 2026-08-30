<?php

namespace App\Services;

use App\Models\City;
use App\Models\Country;
use App\Models\TimezoneReference;
use DateTimeZone;
use Illuminate\Support\Str;
use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

class LocationDatasetImporter
{
    public function __construct(private LeadNormalizationService $normalizer) {}

    /** @return array{countries: int, cities: int} */
    public function import(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("Location dataset not found: {$path}");
        }
        $countries = 0;
        $cities = 0;
        foreach ($this->rows($path) as $row) {
            $name = trim((string) ($row['Country'] ?? ''));
            $iso2 = strtoupper(trim((string) ($row['Original Country Code'] ?? $row['Code'] ?? '')));
            if ($name === '' || mb_strlen($iso2) !== 2) {
                continue;
            }
            $referenceCode = strtoupper(trim((string) ($row['Code'] ?? $iso2)));
            $capital = trim((string) ($row['Capital'] ?? ''));
            $timezone = $this->timezone($referenceCode, $capital);
            TimezoneReference::query()->updateOrCreate(
                ['original_country_code' => $iso2],
                ['country' => $name, 'reference_country_code' => $referenceCode, 'reference_capital' => $capital],
            );
            $country = Country::query()->updateOrCreate(['iso2' => $iso2], ['name' => $name, 'normalized_name' => $this->normalizer->normalizeLocation($name), 'default_timezone' => $timezone]);
            $countries++;
            if ($capital !== '' && $timezone !== null && $referenceCode === $iso2) {
                City::query()->updateOrCreate(['country_id' => $country->id, 'normalized_name' => $this->normalizer->normalizeLocation($capital), 'state_province' => null], ['name' => $capital, 'timezone' => $timezone]);
                $cities++;
            }
        }

        return ['countries' => $countries, 'cities' => $cities];
    }

    /** @return iterable<array<string, string|null>> */
    private function rows(string $path): iterable
    {
        if (Str::lower(pathinfo($path, PATHINFO_EXTENSION)) === 'csv') {
            $stream = fopen($path, 'rb');
            if (! is_resource($stream)) {
                throw new RuntimeException('The location CSV could not be opened.');
            }
            $headers = fgetcsv($stream, escape: '');
            while (is_array($headers) && ($values = fgetcsv($stream, escape: '')) !== false) {
                yield array_combine($headers, array_pad($values, count($headers), null));
            }
            fclose($stream);

            return;
        }

        yield from $this->xlsxRows($path);
    }

    /** @return iterable<array<string, string|null>> */
    private function xlsxRows(string $path): iterable
    {
        $archive = new ZipArchive;
        if ($archive->open($path) !== true) {
            throw new RuntimeException('The XLSX location dataset could not be opened.');
        }
        $sharedXml = $archive->getFromName('xl/sharedStrings.xml');
        $sheetXml = $archive->getFromName('xl/worksheets/sheet1.xml');
        if (! is_string($sharedXml) || ! is_string($sheetXml)) {
            $archive->close();
            throw new RuntimeException('The XLSX workbook is missing its first worksheet.');
        }
        $sharedRoot = simplexml_load_string($sharedXml);
        $sheetRoot = simplexml_load_string($sheetXml);
        if (! $sharedRoot || ! $sheetRoot) {
            $archive->close();
            throw new RuntimeException('The XLSX workbook XML is invalid.');
        }
        $shared = array_map(fn (SimpleXMLElement $item): string => implode('', array_map('strval', $item->xpath('.//*[local-name()="t"]') ?: [])), $sharedRoot->xpath('//*[local-name()="si"]') ?: []);
        $rows = $sheetRoot->xpath('//*[local-name()="sheetData"]/*[local-name()="row"]') ?: [];
        $headers = [];
        foreach ($rows as $rowIndex => $row) {
            $values = [];
            foreach ($row->xpath('./*[local-name()="c"]') ?: [] as $cell) {
                preg_match('/^[A-Z]+/', (string) $cell['r'], $matches);
                $column = $this->columnIndex($matches[0] ?? 'A');
                $valueNodes = $cell->xpath('./*[local-name()="v"]');
                $value = (string) ($valueNodes[0] ?? '');
                $values[$column] = (string) $cell['t'] === 's' ? ($shared[(int) $value] ?? '') : $value;
            }
            if ($rowIndex === 0) {
                $headers = $values;

                continue;
            }
            $record = [];
            foreach ($headers as $column => $header) {
                $record[$header] = $values[$column] ?? null;
            }
            yield $record;
        }
        $archive->close();
    }

    private function columnIndex(string $letters): int
    {
        $index = 0;
        foreach (str_split($letters) as $letter) {
            $index = ($index * 26) + ord($letter) - 64;
        }

        return $index - 1;
    }

    private function timezone(string $countryCode, string $capital): ?string
    {
        if (mb_strlen($countryCode) !== 2) {
            return null;
        }
        $identifiers = DateTimeZone::listIdentifiers(DateTimeZone::PER_COUNTRY, $countryCode);
        if ($identifiers === []) {
            return null;
        }
        $capitalSlug = Str::of($capital)->ascii()->replace(' ', '_')->lower()->toString();

        return collect($identifiers)->first(fn (string $identifier): bool => Str::of($identifier)->afterLast('/')->lower()->toString() === $capitalSlug) ?? $identifiers[0];
    }
}
