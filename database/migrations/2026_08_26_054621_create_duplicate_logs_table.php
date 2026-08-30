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
        Schema::create('duplicate_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('uploading_agent_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('original_lead_id')->constrained('leads')->cascadeOnDelete();
            $table->foreignId('original_owner_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('upload_batch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('upload_row_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('duplicate_match_id')->nullable()->constrained()->nullOnDelete();
            $table->string('detection_reason');
            $table->timestamps();
            $table->index(['uploading_agent_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('duplicate_logs');
    }
};
