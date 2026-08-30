<?php

namespace App\Console\Commands;

use App\Services\EmailSequenceProcessor;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('email-sequences:process')]
#[Description('Send due email sequence steps and stop sequences that received replies')]
class ProcessEmailSequencesCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(EmailSequenceProcessor $processor): int
    {
        $processed = $processor->processDue();
        $this->info("Processed {$processed} due email sequence enrollments.");

        return self::SUCCESS;
    }
}
