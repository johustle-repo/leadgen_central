<?php

namespace App\Services;

class CsvCellSanitizer
{
    public function sanitize(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $trimmed = ltrim($value);

        return $trimmed !== '' && in_array($trimmed[0], ['=', '+', '-', '@'], true)
            ? "'{$value}"
            : $value;
    }

    /**
     * @param  array<int, mixed>  $values
     * @return array<int, mixed>
     */
    public function sanitizeRow(array $values): array
    {
        return array_map($this->sanitize(...), $values);
    }
}
