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
        Schema::table('upload_rows', function (Blueprint $table) {
            $table->string('error_category')->nullable()->index()->after('processing_status');
            $table->foreignId('duplicate_match_id')->nullable()->after('lead_id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('upload_rows', function (Blueprint $table) {
            $table->dropConstrainedForeignId('duplicate_match_id');
            $table->dropColumn('error_category');
        });
    }
};
