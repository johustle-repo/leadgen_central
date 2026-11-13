<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Widen the source and LinkedIn links so long URLs fit untruncated.
     */
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->text('source_url')->nullable()->change();
            $table->text('linkedin_url')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('source_url')->nullable()->change();
            $table->string('linkedin_url')->nullable()->change();
        });
    }
};
