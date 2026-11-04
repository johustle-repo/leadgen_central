<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Payroll no longer tracks utility bills.
     */
    public function up(): void
    {
        Schema::dropIfExists('payroll_months');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
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
};
