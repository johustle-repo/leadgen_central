<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property \Carbon\CarbonInterface $holiday_date
 * @property string $name
 * @property string $country_code
 * @property string $type
 * @property string|null $notes
 */
class Holiday extends Model
{
    protected $fillable = ['holiday_date', 'name', 'country_code', 'type', 'notes'];

    protected function casts(): array
    {
        return [
            'holiday_date' => 'date',
        ];
    }
}
