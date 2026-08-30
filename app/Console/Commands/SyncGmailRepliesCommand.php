<?php

namespace App\Console\Commands;

use App\Jobs\SyncGmailReplies;
use App\Models\GmailConnection;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('gmail:sync')]
#[Description('Queue synchronization for every active Gmail connection')]
class SyncGmailRepliesCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        GmailConnection::query()->where('status', 'active')->pluck('id')->each(fn (int $id) => SyncGmailReplies::dispatch($id));
        $this->info('Gmail reply synchronization was queued.');

        return self::SUCCESS;
    }
}
