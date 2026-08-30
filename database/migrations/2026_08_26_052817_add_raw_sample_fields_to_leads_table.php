<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->date('lead_date')->nullable()->index()->after('source');
            $table->string('import_trades')->nullable()->after('linkedin_url');
            $table->string('data_source')->nullable()->index()->after('import_trades');
            $table->string('source_url')->nullable()->after('data_source');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['lead_date']);
            $table->dropIndex(['data_source']);
            $table->dropColumn(['lead_date', 'import_trades', 'data_source', 'source_url']);
        });
    }
};
