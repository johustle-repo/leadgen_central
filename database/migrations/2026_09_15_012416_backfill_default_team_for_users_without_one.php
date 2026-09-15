<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Every current user is Philippines-based and none had a team on file,
     * so the Users list showed a blank "—" for all of them. Backfill "PH"
     * as their team.
     */
    public function up(): void
    {
        DB::table('users')->where(function ($query) {
            $query->whereNull('team')->orWhere('team', '');
        })->update(['team' => 'PH']);
    }

    /**
     * A one-time data backfill isn't meaningfully reversible - rolling back
     * would blank out a team an administrator may have since edited.
     */
    public function down(): void {}
};
