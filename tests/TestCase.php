<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;
use LogicException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $application = parent::createApplication();

        if (! $application->environment('testing')
            || $application->make('config')->get('database.default') !== 'sqlite'
            || $application->make('config')->get('database.connections.sqlite.database') !== ':memory:') {
            throw new LogicException(
                'Tests must use the in-memory SQLite database. Run php artisan optimize:clear before testing.',
            );
        }

        return $application;
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
