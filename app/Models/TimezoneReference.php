<?php

namespace App\Models;

use Database\Factories\TimezoneReferenceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TimezoneReference extends Model
{
    /** @use HasFactory<TimezoneReferenceFactory> */
    use HasFactory;

    protected $fillable = [
        'country',
        'original_country_code',
        'reference_country_code',
        'reference_capital',
    ];
}
