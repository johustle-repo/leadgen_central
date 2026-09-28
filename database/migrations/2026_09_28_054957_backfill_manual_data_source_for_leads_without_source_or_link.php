<?php

use App\Support\ReportCache;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Marks every existing lead, imported or hand-entered, with neither a data
     * source nor a link (blank or a placeholder such as "N/A") as Manual,
     * matching the rule the Lead model now applies on save.
     */
    public function up(): void
    {
        $placeholders = "('', 'n/a', 'na', 'none', 'null', '-')";

        DB::table('leads')
            ->where(fn ($query) => $query->whereNull('data_source')->orWhereRaw("LOWER(TRIM(data_source)) IN {$placeholders}"))
            ->where(fn ($query) => $query->whereNull('source_url')->orWhereRaw("LOWER(TRIM(source_url)) IN {$placeholders}"))
            ->update(['data_source' => 'Manual']);

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
