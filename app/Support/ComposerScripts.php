<?php

namespace App\Support;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\PackageManifest;

class ComposerScripts
{
    public static function postAutoloadDump(): void
    {
        require_once dirname(__DIR__, 2).'/vendor/autoload.php';

        /** @var Application $application */
        $application = require dirname(__DIR__, 2).'/bootstrap/app.php';
        $files = new Filesystem;
        $application->instance('files', $files);

        $files->delete([
            $application->getCachedConfigPath(),
            $application->getCachedServicesPath(),
            $application->getCachedPackagesPath(),
        ]);

        $application->make(PackageManifest::class)->build();
    }
}
