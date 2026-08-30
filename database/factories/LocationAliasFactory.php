<?php

namespace Database\Factories;

use App\Models\LocationAlias;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LocationAlias>
 */
class LocationAliasFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $alias = fake()->unique()->city();

        return ['alias' => $alias, 'normalized_alias' => str($alias)->lower()->ascii()->toString()];
    }
}
