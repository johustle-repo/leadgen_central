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
        Schema::create('email_sequence_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_sequence_enrollment_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('step_number');
            $table->string('gmail_message_id')->nullable()->index();
            $table->string('gmail_thread_id')->nullable()->index();
            $table->string('subject');
            $table->longText('body');
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->unique(['email_sequence_enrollment_id', 'step_number'], 'sequence_message_step_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_sequence_messages');
    }
};
