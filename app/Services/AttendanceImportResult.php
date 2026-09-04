<?php

namespace App\Services;

class AttendanceImportResult
{
    /**
     * @param  list<string>  $errors
     */
    public function __construct(
        public readonly int $total,
        public readonly int $imported,
        public readonly int $duplicates,
        public readonly int $holidaysImported,
        public readonly array $errors,
    ) {}
}
