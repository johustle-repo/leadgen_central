<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The Reports page's Geographic Analysis and distribution panels
     * (app/Services/DatabaseIntelligenceReport.php, app/Services/DashboardReport.php)
     * now actively group and filter leads by state_province and timezone, alongside
     * the already-indexed city/country/country_code columns. Neither had an index.
     */
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->index('state_province');
            $table->index('timezone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['state_province']);
            $table->dropIndex(['timezone']);
        });
    }
};
