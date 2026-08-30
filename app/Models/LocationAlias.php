<?php

namespace App\Models;

use Database\Factories\LocationAliasFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LocationAlias extends Model
{
    /** @use HasFactory<LocationAliasFactory> */
    use HasFactory;

    protected $fillable = ['alias', 'normalized_alias', 'city_id', 'country_id', 'approved_by'];

    /** @return BelongsTo<City, $this> */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /** @return BelongsTo<Country, $this> */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }
}
