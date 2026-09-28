<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

return new class extends Migration
{
    /**
     * Applies the state/province rule to existing US and Canadian leads once;
     * `php artisan leads:normalize-states --dry-run` previews the same run and
     * lists the cities it could not place.
     */
    public function up(): void
    {
        Artisan::call('leads:normalize-states');
    }

    /**
     * The original text of every rewritten City is kept in raw_city.
     */
    public function down(): void
    {
        //
    }
};
