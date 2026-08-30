<?php

namespace App\Console\Commands;

use App\Services\LocationDatasetImporter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('locations:import {path : Absolute path to a CSV or XLSX dataset}')]
#[Description('Import canonical countries, capital cities, and IANA timezone mappings')]
class ImportLocationDataset extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(LocationDatasetImporter $importer): int
    {
        try {
            $counts = $importer->import((string) $this->argument('path'));
            $this->info("Imported {$counts['countries']} countries and {$counts['cities']} canonical cities.");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
