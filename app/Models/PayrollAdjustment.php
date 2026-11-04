<?php

namespace App\Models;

use App\EmploymentType;
use Carbon\CarbonInterface;
use Database\Factories\PayrollAdjustmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A one-month exception to a member's payroll: the category they were
 * paid under that month and/or a fixed number of paid days, overriding
 * what attendance would calculate.
 *
 * @property int $id
 * @property int $user_id
 * @property CarbonInterface $month
 * @property EmploymentType|null $employment_type
 * @property string|null $paid_days
 */
class PayrollAdjustment extends Model
{
    /** @use HasFactory<PayrollAdjustmentFactory> */
    use HasFactory;

    protected $fillable = ['user_id', 'month', 'employment_type', 'paid_days'];

    protected function casts(): array
    {
        return [
            'month' => 'date',
            'employment_type' => EmploymentType::class,
            'paid_days' => 'decimal:3',
        ];
    }

    /**
     * Saves a member's adjustment for one month, or removes it when
     * neither a category nor paid days are given.
     */
    public static function setFor(User $user, CarbonInterface $month, ?EmploymentType $employmentType, int|float|string|null $paidDays): void
    {
        $monthStart = $month->copy()->startOfMonth()->toDateString();
        $existing = self::query()->where('user_id', $user->id)->whereDate('month', $monthStart)->first();

        if ($employmentType === null && ($paidDays === null || $paidDays === '')) {
            $existing?->delete();

            return;
        }

        ($existing ?? new self(['user_id' => $user->id, 'month' => $monthStart]))
            ->fill(['employment_type' => $employmentType, 'paid_days' => $paidDays === '' ? null : $paidDays])
            ->save();
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
