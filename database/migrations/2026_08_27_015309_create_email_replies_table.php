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
        Schema::create('email_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gmail_connection_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('agent_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->string('gmail_message_id');
            $table->string('gmail_thread_id')->index();
            $table->string('sender_name')->nullable();
            $table->string('sender_email')->index();
            $table->string('subject')->nullable();
            $table->text('body_preview')->nullable();
            $table->longText('body_text')->nullable();
            $table->string('classification')->default('needs_review')->index();
            $table->string('classification_reason')->nullable();
            $table->boolean('is_read')->default(false)->index();
            $table->timestamp('received_at')->index();
            $table->timestamps();
            $table->index(['agent_id', 'is_read', 'received_at']);
            $table->index(['lead_id', 'received_at']);
            $table->unique(['gmail_connection_id', 'gmail_message_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_replies');
    }
};
