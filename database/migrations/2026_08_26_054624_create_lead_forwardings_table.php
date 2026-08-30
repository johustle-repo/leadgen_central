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
        Schema::create('lead_forwardings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('forwarded_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('forwarded_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('recipient_name')->nullable();
            $table->string('recipient_email')->nullable();
            $table->string('team')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamp('forwarded_at');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['lead_id', 'forwarded_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lead_forwardings');
    }
};
