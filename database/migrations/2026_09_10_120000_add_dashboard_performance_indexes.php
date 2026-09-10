<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The dashboard and leads list run several queries that were never scoped by
     * agent_id for an administrator or super administrator (canViewAllLeads()),
     * so they fall back to a full scan of the leads/upload_batches tables on every
     * page load once those tables grow into the tens of thousands of rows:
     *  - DashboardController's total/qualified/unique-company counts filter only
     *    by created_at when the viewer can see every agent's leads.
     *  - The same applies to upload_batches for duplicates_flagged/data_issues.
     *  - LeadController::index's company_contact_count subquery is correlated on
     *    (agent_id, normalized_company_name) per row of every paginated page.
     */
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->index('created_at');
            $table->index(['agent_id', 'normalized_company_name']);
        });

        Schema::table('upload_batches', function (Blueprint $table) {
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropIndex(['agent_id', 'normalized_company_name']);
        });

        Schema::table('upload_batches', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
        });
    }
};
