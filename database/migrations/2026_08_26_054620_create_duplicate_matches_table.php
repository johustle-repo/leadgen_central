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
        Schema::create('duplicate_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incoming_lead_id')->nullable()->constrained('leads')->cascadeOnDelete();
            $table->foreignId('upload_row_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('existing_lead_id')->constrained('leads')->cascadeOnDelete();
            $table->string('match_type')->index();
            $table->unsignedTinyInteger('match_score')->nullable();
            $table->json('matched_fields');
            $table->string('status')->default('pending')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['existing_lead_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('duplicate_matches');
    }
};
