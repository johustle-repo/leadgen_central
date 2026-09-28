<?php

use App\Support\ReportCache;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Brings existing leads in line with the rules the Lead model now applies
     * on save: imports stored "N/A" for a missing LinkedIn, which is cleared,
     * and leads with no data source but a Tendata link are marked Tendata.
     */
    public function up(): void
    {
        $placeholders = "('', 'n/a', 'na', 'none', 'null', '-')";

        DB::table('leads')
            ->whereRaw("LOWER(TRIM(linkedin_url)) IN {$placeholders}")
            ->update(['linkedin_url' => null]);

        DB::table('leads')
            ->where(fn ($query) => $query->whereNull('data_source')->orWhereRaw("LOWER(TRIM(data_source)) IN {$placeholders}"))
            ->whereRaw("LOWER(source_url) LIKE '%tendata.%'")
            ->update(['data_source' => 'Tendata']);

        ReportCache::flush();
    }

    /**
     * The cleared placeholders carried no information, so there is nothing to restore.
     */
    public function down(): void
    {
        //
    }
};
