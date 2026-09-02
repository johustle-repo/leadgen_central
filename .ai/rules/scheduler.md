---
paths:
  - 'routes/console.php'
---

# Scheduler

## Never schedule tasks via Schedule::command()/Schedule::exec()
`Schedule::command()` and `Schedule::exec()` shell out through Symfony `Process` (`proc_open`). The production host (Hostinger shared hosting) disables `proc_open`, so a command-string schedule entry registers and appears in `schedule:list`, but silently never executes even though cron correctly fires `schedule:run` every minute — there is no error, the task just never runs. This previously left lead CSV uploads stuck at "Pending" forever because the scheduled `queue:work` and `uploads:dispatch-pending` tasks never actually ran.

Always schedule recurring work with `Schedule::call(fn () => Artisan::call('command:name', [...]))->name('unique-name')`, which executes in-process and has no `proc_open` dependency. `->name()` is required before `withoutOverlapping()`/`onOneServer()` on a callback event. See `tests/Feature/DeploymentReadinessTest.php` (asserts every scheduled event is a `CallbackEvent`) and `tests/Feature/DispatchPendingUploadBatchesTest.php` (asserts `schedule:run` alone unsticks a stale pending upload) for the regression coverage.

This host has no supervisor for a persistent `queue:work` process either, so the scheduler itself drains the database queue every minute (`--stop-when-empty --max-time=50`) instead of relying on a long-running worker.
