<?php

use App\Models\Lead;
use App\Support\ReportCache;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Rewrites data sources imported with another spelling of a known source
     * (such as "TENDATA/LUSHA") to the spelling the lead form offers, so the
     * form's Sources of Data select shows them. New saves do this in the model.
     */
    public function up(): void
    {
        foreach (Lead::DATA_SOURCES as $source) {
            DB::table('leads')->whereRaw("LOWER(REPLACE(data_source, ' ', '')) = ?", [mb_strtolower($source)])
                ->update(['data_source' => $source]);
        }

        ReportCache::flush();
    }

    /**
     * The original spellings are not kept, so there is nothing to restore.
     */
    public function down(): void
    {
        //
    }
};
