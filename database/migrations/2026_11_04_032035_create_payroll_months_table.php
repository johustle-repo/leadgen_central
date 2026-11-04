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
        Schema::create('payroll_months', function (Blueprint $table) {
            $table->id();
            $table->date('month')->unique();
            $table->decimal('usd_to_php_rate', 10, 4)->nullable();
            $table->decimal('internet_php', 12, 2)->default(0);
            $table->decimal('electricity_php', 12, 2)->default(0);
            $table->decimal('water_php', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_months');
    }
};
