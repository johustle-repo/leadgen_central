<?php

namespace App\Services;

class CsvEncodingSanitizer
{
    /**
     * Windows/Excel exports are routinely saved as Windows-1252 rather than
     * UTF-8, so a curly quote, em dash, or accented name is enough to make
     * json_encode() (used when persisting the array-cast raw_data/headers
     * columns) fail outright - and that failure happens outside any per-row
     * error handling, so it took down the entire batch instead of just that
     * one row. Reinterpreting invalid bytes as Windows-1252 recovers the
     * intended character instead of merely discarding it; anything still
     * invalid afterward is dropped so the result is always valid UTF-8.
     */
    public function toUtf8(?string $value): ?string
    {
        if ($value === null || $value === '' || mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        $converted = @mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
        if (mb_check_encoding($converted, 'UTF-8')) {
            return $converted;
        }

        return (string) @iconv('UTF-8', 'UTF-8//IGNORE', $value);
    }

    /**
     * @param  list<string>  $values
     * @return list<string>
     */
    public function sanitizeRow(array $values): array
    {
        return array_map($this->toUtf8(...), $values);
    }
}
