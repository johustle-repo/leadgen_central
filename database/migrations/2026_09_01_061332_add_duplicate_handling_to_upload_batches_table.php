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
            $table->string('duplicate_handling')->default('flag')->after('column_mapping');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('upload_batches', function (Blueprint $table) {
            $table->dropColumn('duplicate_handling');
        });
    }
};
