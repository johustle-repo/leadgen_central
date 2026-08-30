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
        Schema::create('email_sequence_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_sequence_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('active')->index();
            $table->unsignedTinyInteger('current_step')->default(0);
            $table->timestamp('started_at')->index();
            $table->timestamp('next_send_at')->nullable()->index();
            $table->timestamp('stopped_at')->nullable();
            $table->string('stop_reason')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['email_sequence_id', 'lead_id']);
            $table->index(['status', 'next_send_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_sequence_enrollments');
    }
};
