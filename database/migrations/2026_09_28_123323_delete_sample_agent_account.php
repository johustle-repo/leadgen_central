<?php

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Removes the "Sample Agent" demo account the seeder used to create, the
     * same way the Users page deletes an account (soft delete): its leads
     * stay and show a deleted owner. The seeder no longer creates it.
     */
    public function up(): void
    {
        User::query()->where($this->sampleAgent(...))->get()->each->delete();
    }

    /**
     * Restores the demo account if it was removed here.
     */
    public function down(): void
    {
        User::onlyTrashed()->where($this->sampleAgent(...))->get()->each->restore();
    }

    /**
     * The seeded demo agent: its default email, or its seeded name and role
     * when LEADGEN_AGENT_EMAIL gave it another email.
     *
     * @param  Builder<User>  $query
     */
    private function sampleAgent(Builder $query): void
    {
        $query->where('email', 'agent@leadgen.test')
            ->orWhere(fn (Builder $seeded) => $seeded->where('name', 'Sample Agent')->where('role', 'agent'));
    }
};
