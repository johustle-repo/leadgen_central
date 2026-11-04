<?php

use App\Models\Attendance;
use App\Models\PayrollMonth;
use App\Models\User;
use Illuminate\Support\Facades\View;
use Illuminate\View\View as RenderedView;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Records a full 8-hour day (07:00-17:00) for each date.
 *
 * @param  list<string>  $dates
 */
function recordFullDays(User $user, array $dates): void
{
    foreach ($dates as $date) {
        Attendance::factory()->for($user)->create(['entry_type' => 'time_in', 'recorded_at' => "{$date} 07:00:00"]);
        Attendance::factory()->for($user)->create(['entry_type' => 'time_out', 'recorded_at' => "{$date} 17:00:00"]);
    }
}

it('allows only the super administrator to use payroll', function (Closure $makeUser) {
    $user = $makeUser();
    $member = User::factory()->create();

    $this->actingAs($user)->get(route('payroll.index'))->assertForbidden();
    $this->actingAs($user)->get(route('payroll.export-pdf'))->assertForbidden();
    $this->actingAs($user)->put(route('payroll.members.update', $member), ['monthly_salary' => 999, 'employment_type' => 'full_time', 'joined_on' => '2026-03-20'])->assertForbidden();
    $this->actingAs($user)->put(route('payroll.months.update', '2026-04'), ['internet_php' => 1, 'electricity_php' => 1, 'water_php' => 1])->assertForbidden();

    expect($member->refresh()->monthly_salary)->toBeNull()
        ->and(PayrollMonth::query()->count())->toBe(0);
})->with([
    'administrator' => fn () => User::factory()->administrator()->create(),
    'agent' => fn () => User::factory()->create(),
]);

it('pays every day from the join date minus absent workdays', function () {
    $this->travelTo(Carbon\Carbon::parse('2026-04-15 04:00:00', 'UTC'));
    $superAdministrator = User::factory()->superAdministrator()->create();
    $member = User::factory()->create(['name' => 'Alex Member', 'monthly_salary' => 400, 'joined_on' => '2026-03-20']);
    User::factory()->create(['name' => 'No Salary Yet', 'joined_on' => '2026-03-01']);
    User::factory()->create(['name' => 'Joined Later', 'monthly_salary' => 400, 'joined_on' => '2026-04-01']);
    recordFullDays($member, ['2026-03-20', '2026-03-21', '2026-03-23', '2026-03-25', '2026-03-26', '2026-03-27', '2026-03-28', '2026-03-30', '2026-03-31']);

    $this->actingAs($superAdministrator)->get(route('payroll.index', ['month' => '2026-03']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('payroll/index')
            ->where('payroll.days_in_month', 31)
            ->has('payroll.members', 2)
            ->where('payroll.members.0.name', 'Alex Member')
            ->where('payroll.members.0.employed_days', 12)
            ->where('payroll.members.0.absent_days', 1)
            ->where('payroll.members.0.paid_days', 11)
            ->where('payroll.members.0.day_rate', 12.9)
            ->where('payroll.members.0.hour_rate', 1.61)
            ->where('payroll.members.0.pay', 141.94)
            ->where('payroll.members.1.name', 'No Salary Yet')
            ->where('payroll.members.1.pay', null)
            ->where('payroll.totals.salary', 141.94)
            ->where('payroll.totals.missing_salary_count', 1));
});

it('pays part-time members for hours worked divided by eight', function () {
    $this->travelTo(Carbon\Carbon::parse('2026-05-15 04:00:00', 'UTC'));
    $superAdministrator = User::factory()->superAdministrator()->create();
    $member = User::factory()->create(['monthly_salary' => 400, 'employment_type' => 'part_time', 'joined_on' => '2026-03-20']);
    recordFullDays($member, ['2026-04-06', '2026-04-07']);

    $this->actingAs($superAdministrator)->get(route('payroll.index', ['month' => '2026-04']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('payroll.members.0.worked_minutes', 960)
            ->where('payroll.members.0.paid_days', 2)
            ->where('payroll.members.0.pay', 26.67));
});

it('saves a member salary, category and join date with an audit entry', function () {
    $superAdministrator = User::factory()->superAdministrator()->create();
    $member = User::factory()->create();

    $this->actingAs($superAdministrator)
        ->put(route('payroll.members.update', $member), ['monthly_salary' => '550.00', 'employment_type' => 'part_time', 'joined_on' => '2026-03-20'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $member->refresh();
    expect($member->monthly_salary)->toBe('550.00')
        ->and($member->employment_type->value)->toBe('part_time')
        ->and($member->joined_on->toDateString())->toBe('2026-03-20');
    $this->assertDatabaseHas('audit_logs', ['user_id' => $superAdministrator->id, 'action' => 'payroll.member_updated', 'auditable_id' => $member->id]);
});

it('rejects invalid member payroll details', function () {
    $superAdministrator = User::factory()->superAdministrator()->create();
    $member = User::factory()->create();

    $this->actingAs($superAdministrator)
        ->put(route('payroll.members.update', $member), ['monthly_salary' => -1, 'employment_type' => 'contractor', 'joined_on' => 'soon'])
        ->assertSessionHasErrors(['monthly_salary', 'employment_type', 'joined_on']);

    expect($member->refresh()->monthly_salary)->toBeNull();
});

it('converts the month utilities to USD and adds them to the total to pay', function () {
    $this->travelTo(Carbon\Carbon::parse('2026-05-15 04:00:00', 'UTC'));
    $superAdministrator = User::factory()->superAdministrator()->create();
    User::factory()->create(['monthly_salary' => 400, 'joined_on' => '2026-03-20']);

    $this->actingAs($superAdministrator)
        ->put(route('payroll.months.update', '2026-04'), ['usd_to_php_rate' => 61.43, 'internet_php' => 1798.81, 'electricity_php' => 5679.41, 'water_php' => 0])
        ->assertSessionHasNoErrors();
    $this->actingAs($superAdministrator)
        ->put(route('payroll.months.update', '2026-04'), ['usd_to_php_rate' => 61.43, 'internet_php' => 1798.81, 'electricity_php' => 5679.41, 'water_php' => 0])
        ->assertSessionHasNoErrors();

    expect(PayrollMonth::query()->count())->toBe(1);
    $this->actingAs($superAdministrator)->get(route('payroll.index', ['month' => '2026-04']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('payroll.utilities.items.0.usd', 29.28)
            ->where('payroll.utilities.items.1.usd', 92.45)
            ->where('payroll.totals.service', 121.73)
            ->where('payroll.totals.salary', 53.33)
            ->where('payroll.totals.to_pay', 175.06));
});

it('rejects invalid utilities', function () {
    $superAdministrator = User::factory()->superAdministrator()->create();

    $this->actingAs($superAdministrator)
        ->put(route('payroll.months.update', '2026-04'), ['usd_to_php_rate' => 0, 'internet_php' => -5, 'electricity_php' => '', 'water_php' => 0])
        ->assertSessionHasErrors(['usd_to_php_rate', 'internet_php', 'electricity_php']);

    expect(PayrollMonth::query()->count())->toBe(0);
});

it('downloads the payroll as a PDF and records the export', function () {
    $this->travelTo(Carbon\Carbon::parse('2026-05-15 04:00:00', 'UTC'));
    $superAdministrator = User::factory()->superAdministrator()->create();
    User::factory()->create(['name' => 'Sample Agent', 'monthly_salary' => 400, 'joined_on' => '2026-03-20']);

    $renderedPayroll = null;
    View::composer('payroll.export', function (RenderedView $view) use (&$renderedPayroll): void {
        $renderedPayroll = $view->getData();
    });

    $response = $this->actingAs($superAdministrator)->get(route('payroll.export-pdf', ['month' => '2026-04']));

    $response->assertOk()->assertDownload('Payroll_April_2026.pdf');
    expect($response->headers->get('Content-Type'))->toBe('application/pdf');
    expect(view('payroll.export', $renderedPayroll)->render())
        ->toContain('Sample Agent')
        ->toContain('USD 13.33')
        ->toContain('USD 1.67')
        ->toContain('USD 400.00');
    $this->assertDatabaseHas('audit_logs', ['user_id' => $superAdministrator->id, 'action' => 'payroll.exported_pdf']);
});
