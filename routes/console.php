<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * Every scheduled task below runs through Schedule::call()/Artisan::call() rather
 * than Schedule::command(), which shells out via Symfony Process (proc_open).
 * Shared hosts such as Hostinger disable proc_open, so a command-string schedule
 * silently fails to spawn and the task never actually runs even though cron is
 * firing `schedule:run` every minute. Artisan::call() executes the command
 * in-process instead, so it works regardless of proc_open availability.
 */
Schedule::call(fn () => Artisan::call('gmail:sync'))
    ->name('gmail-sync')
    ->everyMinute()
    ->onOneServer()
    ->withoutOverlapping(5);

Schedule::call(fn () => Artisan::call('email-sequences:process'))
    ->name('email-sequences-process')
    ->everyMinute()
    ->onOneServer()
    ->withoutOverlapping(5);

Schedule::call(fn () => Artisan::call('uploads:dispatch-pending'))
    ->name('uploads-dispatch-pending')
    ->everyMinute()
    ->onOneServer()
    ->withoutOverlapping(5);

Schedule::call(fn () => Artisan::call('queue:work', [
    'connection' => 'database',
    '--queue' => 'default',
    '--stop-when-empty' => true,
    '--max-time' => 50,
    '--timeout' => 0,
    '--tries' => 3,
]))
    ->name('queue-work-database')
    ->everyMinute()
    ->onOneServer()
    ->withoutOverlapping(5);
