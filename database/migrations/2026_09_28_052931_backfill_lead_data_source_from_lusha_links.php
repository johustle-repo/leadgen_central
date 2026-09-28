<?php

use App\Support\ReportCache;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Marks existing leads with no data source but a Lusha link as Lusha,
     * matching the rule the Lead model now applies on save.
     */
    public function up(): void
    {
        DB::table('leads')
            ->where(fn ($query) => $query->whereNull('data_source')->orWhereRaw("LOWER(TRIM(data_source)) IN ('', 'n/a', 'na', 'none', 'null', '-')"))
            ->whereRaw("LOWER(source_url) LIKE '%lusha.%'")
            ->update(['data_source' => 'Lusha']);

        ReportCache::flush();
    }

    /**
     * The replaced values were empty placeholders, so there is nothing to restore.
     */
    public function down(): void
    {
        //
    }
};
