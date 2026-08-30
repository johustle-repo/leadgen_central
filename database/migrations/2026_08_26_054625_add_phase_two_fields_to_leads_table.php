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
            $table->string('original_website')->nullable()->after('website');
            $table->string('raw_city')->nullable()->after('city');
            $table->string('raw_country')->nullable()->after('country');
            $table->foreignId('canonical_city_id')->nullable()->after('raw_country')->constrained('cities')->nullOnDelete();
            $table->foreignId('canonical_country_id')->nullable()->after('canonical_city_id')->constrained('countries')->nullOnDelete();
            $table->string('validation_status')->default('pending')->index()->after('status');
            $table->string('location_match_type')->nullable()->index()->after('validation_status');
            $table->timestamp('verified_at')->nullable()->index()->after('location_match_type');
            $table->foreignId('verified_by')->nullable()->after('verified_at')->constrained('users')->nullOnDelete();
            $table->index(['normalized_company_name', 'city', 'country_code'], 'leads_company_location_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex('leads_company_location_index');
            $table->dropConstrainedForeignId('verified_by');
            $table->dropConstrainedForeignId('canonical_country_id');
            $table->dropConstrainedForeignId('canonical_city_id');
            $table->dropColumn(['original_website', 'raw_city', 'raw_country', 'validation_status', 'location_match_type', 'verified_at']);
        });
    }
};
