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
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('monthly_salary', 10, 2)->nullable()->after('alias_email');
            $table->string('employment_type')->default('full_time')->after('monthly_salary');
            $table->date('joined_on')->nullable()->after('employment_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['monthly_salary', 'employment_type', 'joined_on']);
        });
    }
};
