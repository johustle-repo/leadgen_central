<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\PayrollMonthFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Per-month payroll inputs that don't come from attendance: the USD to
 * PHP exchange rate and the utility bills paid alongside salaries.
 *
 * @property int $id
 * @property CarbonInterface $month
 * @property string|null $usd_to_php_rate
 * @property string $internet_php
 * @property string $electricity_php
 * @property string $water_php
 */
class PayrollMonth extends Model
{
    /** @use HasFactory<PayrollMonthFactory> */
    use HasFactory;

    protected $fillable = ['month', 'usd_to_php_rate', 'internet_php', 'electricity_php', 'water_php'];

    protected function casts(): array
    {
        return [
            'month' => 'date',
            'usd_to_php_rate' => 'decimal:4',
            'internet_php' => 'decimal:2',
            'electricity_php' => 'decimal:2',
            'water_php' => 'decimal:2',
        ];
    }
}
