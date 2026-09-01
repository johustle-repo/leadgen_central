<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\EmailSequenceEnrollment;
use App\Models\GmailConnection;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class EmailSequenceProcessor
{
    public function __construct(private GmailOAuthService $gmail, private GmailMessageBuilder $messages) {}

    public function processDue(): int
    {
        $processed = 0;
        EmailSequenceEnrollment::query()->where('status', 'active')->whereNotNull('next_send_at')
            ->where('next_send_at', '<=', now())->with(['sequence', 'lead'])->orderBy('id')
            ->whereHas('sequence', fn ($query) => $query->where('is_active', true))
            ->chunkById(50, function ($enrollments) use (&$processed): void {
                foreach ($enrollments as $enrollment) {
                    $this->process($enrollment);
                    $processed++;
                }
            });

        return $processed;
    }

    public function process(EmailSequenceEnrollment $enrollment): void
    {
        $enrollment->loadMissing(['sequence', 'lead']);
        if ($enrollment->lead->emailReplies()->where('received_at', '>=', $enrollment->started_at)->exists()) {
            $this->stopForReply($enrollment);

            return;
        }

        $steps = $enrollment->sequence->steps;
        $step = $steps[$enrollment->current_step] ?? null;
        if (! is_array($step)) {
            $enrollment->update(['status' => 'completed', 'next_send_at' => null, 'stopped_at' => now(), 'stop_reason' => 'All steps sent']);

            return;
        }

        $connection = GmailConnection::query()->where('user_id', $enrollment->agent_id)->where('status', 'active')->first();
        if ($connection === null) {
            $enrollment->update(['status' => 'failed', 'next_send_at' => null, 'last_error' => 'Gmail is not connected.']);

            return;
        }

        try {
            $subject = $this->personalize((string) $step['subject'], $enrollment);
            $body = $this->personalize((string) $step['body'], $enrollment);
            $attachBrochure = $step['attach_brochure'];
            $sent = $this->gmail->sendMessage($connection, $this->messages->build(
                $connection->gmail_address,
                (string) $enrollment->lead->email,
                $subject,
                $body,
                $attachBrochure ? $this->brochurePath() : null,
                $attachBrochure ? (string) config('services.google.brochure_name') : null,
            ));
            $enrollment->messages()->create([
                'step_number' => $enrollment->current_step + 1,
                'gmail_message_id' => $sent['id'] ?? null,
                'gmail_thread_id' => $sent['threadId'] ?? null,
                'subject' => $subject,
                'body' => $body,
                'sent_at' => now(),
            ]);
            $nextIndex = $enrollment->current_step + 1;
            $nextStep = $steps[$nextIndex] ?? null;
            $enrollment->update([
                'current_step' => $nextIndex,
                'status' => $nextStep === null ? 'completed' : 'active',
                'next_send_at' => is_array($nextStep) ? $enrollment->started_at->copy()->addDays(((int) $nextStep['day']) - 1) : null,
                'stopped_at' => $nextStep === null ? now() : null,
                'stop_reason' => $nextStep === null ? 'All steps sent' : null,
                'last_error' => null,
            ]);
            AuditLog::query()->create([
                'user_id' => $enrollment->agent_id,
                'action' => 'email_sequence.message_sent',
                'auditable_type' => 'lead',
                'auditable_id' => $enrollment->lead_id,
                'description' => "Sent sequence step {$nextIndex} to {$enrollment->lead->email}.",
                'metadata' => ['enrollment_id' => $enrollment->id, 'gmail_message_id' => $sent['id'] ?? null],
            ]);
        } catch (Throwable $exception) {
            report($exception);
            $enrollment->update(['status' => 'failed', 'next_send_at' => null, 'last_error' => Str::limit($exception->getMessage(), 1000)]);
        }
    }

    public function stopForReply(EmailSequenceEnrollment $enrollment): void
    {
        if ($enrollment->status === 'active') {
            $enrollment->update(['status' => 'replied', 'next_send_at' => null, 'stopped_at' => now(), 'stop_reason' => 'Lead replied']);
        }
    }

    private function personalize(string $text, EmailSequenceEnrollment $enrollment): string
    {
        $firstName = Str::of((string) $enrollment->lead->contact_person)->trim()->before(' ')->value() ?: 'there';

        return str_replace(['{{firstName}}', '{{companyName}}'], [$firstName, $enrollment->lead->company_name], $text);
    }

    private function brochurePath(): string
    {
        $path = config('services.google.brochure_path');
        if (! is_string($path) || ! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException('The configured DUSCAFF brochure is unavailable.');
        }

        return $path;
    }
}
