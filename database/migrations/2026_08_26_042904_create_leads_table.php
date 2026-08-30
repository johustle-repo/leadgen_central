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
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('lead_code')->nullable()->unique();
            $table->foreignId('agent_id')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('upload_batch_id')->nullable()->index();
            $table->string('source')->index();
            $table->string('company_name')->index();
            $table->string('normalized_company_name')->nullable()->index();
            $table->string('website')->nullable();
            $table->string('website_domain')->nullable()->index();
            $table->string('address')->nullable();
            $table->string('city')->nullable()->index();
            $table->string('state_province')->nullable();
            $table->string('country')->nullable()->index();
            $table->char('country_code', 2)->nullable()->index();
            $table->string('timezone')->nullable();
            $table->string('industry')->nullable()->index();
            $table->string('business_type')->nullable();
            $table->string('contact_person')->nullable()->index();
            $table->string('position')->nullable();
            $table->string('email')->nullable()->index();
            $table->string('phone', 50)->nullable()->index();
            $table->string('linkedin_url')->nullable();
            $table->string('status')->default('raw')->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['agent_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
