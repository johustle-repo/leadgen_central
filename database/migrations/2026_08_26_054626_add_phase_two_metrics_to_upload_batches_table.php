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
        Schema::table('upload_batches', function (Blueprint $table) {
            $table->unsignedInteger('new_leads')->default(0)->after('total_rows');
            $table->unsignedInteger('valid_leads')->default(0)->after('new_leads');
            $table->unsignedInteger('exact_duplicate_rows')->default(0)->after('duplicate_rows');
            $table->unsignedInteger('possible_duplicate_rows')->default(0)->after('exact_duplicate_rows');
            $table->unsignedInteger('invalid_rows')->default(0)->after('rejected_rows');
            $table->unsignedInteger('location_error_rows')->default(0)->after('invalid_rows');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('upload_batches', function (Blueprint $table) {
            $table->dropColumn(['new_leads', 'valid_leads', 'exact_duplicate_rows', 'possible_duplicate_rows', 'invalid_rows', 'location_error_rows']);
        });
    }
};
