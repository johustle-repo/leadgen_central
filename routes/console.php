<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('gmail:sync')->everyMinute()->onOneServer()->withoutOverlapping(5);
Schedule::command('email-sequences:process')->everyMinute()->onOneServer()->withoutOverlapping(5);
Schedule::command('uploads:dispatch-pending')->everyMinute()->onOneServer()->withoutOverlapping(5);
Schedule::command('queue:work database --queue=default --stop-when-empty --max-time=50 --timeout=0 --tries=3')
    ->everyMinute()
    ->onOneServer()
    ->withoutOverlapping(5);
