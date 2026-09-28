<?php

use App\Models\Lead;
use App\Models\User;
use Database\Seeders\RoleUserSeeder;

it('deletes the sample agent account and keeps its leads', function () {
    $sample = User::factory()->create(['name' => 'Sample Agent', 'email' => 'agent@leadgen.test']);
    $renamedEmail = User::factory()->create(['name' => 'Sample Agent', 'email' => 'demo@example.com']);
    $realAgent = User::factory()->create(['name' => 'Ana Cruz', 'email' => 'ana@example.com']);
    $lead = Lead::factory()->for($sample, 'agent')->create();

    $migration = require database_path('migrations/2026_09_28_123323_delete_sample_agent_account.php');
    $migration->up();

    expect($sample->refresh()->trashed())->toBeTrue()
        ->and($renamedEmail->refresh()->trashed())->toBeTrue()
        ->and($realAgent->refresh()->trashed())->toBeFalse()
        ->and(Lead::find($lead->id))->not->toBeNull();

    $migration->down();
    expect($sample->refresh()->trashed())->toBeFalse();
});

it('no longer seeds a sample agent', function () {
    $this->seed(RoleUserSeeder::class);

    expect(User::query()->where('role', 'agent')->exists())->toBeFalse()
        ->and(User::query()->count())->toBe(3);
});
