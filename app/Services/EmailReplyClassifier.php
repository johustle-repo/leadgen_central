<?php

namespace App\Services;

use App\EmailReplyClassification;

class EmailReplyClassifier
{
    /** @return array{classification: EmailReplyClassification, reason: string} */
    public function classify(string $subject, string $body, ?string $autoSubmitted = null): array
    {
        $subjectAndBody = mb_strtolower("{$subject} {$body}");
        $replyBody = mb_strtolower($body);

        if ($this->contains($subjectAndBody, ['delivery status notification', 'delivery failed', 'mail delivery failed', 'undeliverable', 'address not found', 'recipient address rejected', 'mailbox unavailable', 'user unknown', 'message blocked'])) {
            return ['classification' => EmailReplyClassification::Bounce, 'reason' => 'Detected a delivery failure or bounced email response.'];
        }

        if ($this->contains($subjectAndBody, ['retired', 'retirement', 'no longer works here', 'no longer with the company', 'no longer monitoring this email', 'left the company', 'has left our company'])) {
            return ['classification' => EmailReplyClassification::Retired, 'reason' => 'Detected that the contact retired, left the company, or no longer monitors the mailbox.'];
        }

        if ($this->contains($subjectAndBody, ['out of office', 'away from the office', 'currently away', 'on annual leave', 'on vacation', 'limited access to email', 'returning on', 'will return on'])) {
            return ['classification' => EmailReplyClassification::OutOfOffice, 'reason' => 'Detected an out-of-office or absence response.'];
        }

        if (($autoSubmitted !== null && mb_strtolower($autoSubmitted) !== 'no') || $this->contains($subjectAndBody, ['automatic reply', 'auto-reply', 'automated response'])) {
            return ['classification' => EmailReplyClassification::AutomaticReply, 'reason' => 'Detected an automated email response.'];
        }

        if ($this->contains($replyBody, ['remove me', 'unsubscribe', 'do not contact', "don't contact", 'stop emailing', 'take me off your list', 'opt me out'])) {
            return ['classification' => EmailReplyClassification::DoNotContact, 'reason' => 'Detected an explicit request to stop further contact.'];
        }

        if ($this->contains($replyBody, ['not now', 'maybe later', 'reach out later', 'contact me later', 'check back later', 'next month', 'next quarter', 'not at this time', 'not right now'])) {
            return ['classification' => EmailReplyClassification::NotNow, 'reason' => 'Detected interest deferred to a later time.'];
        }

        if ($this->contains($replyBody, ['not interested', 'no thanks', 'no thank you', 'we will pass', 'not a fit', 'wrong person', 'no requirement', 'no need'])) {
            return ['classification' => EmailReplyClassification::NotInterested, 'reason' => 'Detected a clear decline or lack of interest.'];
        }

        if ($this->contains($replyBody, ['interested', 'tell me more', 'send more information', 'more details', 'pricing', 'price list', 'quotation', 'quote', 'schedule a call', 'book a call', 'set up a meeting', 'available for a call'])) {
            return ['classification' => EmailReplyClassification::Interested, 'reason' => 'Detected interest, pricing, or meeting intent.'];
        }

        return ['classification' => EmailReplyClassification::NeedsReview, 'reason' => 'No clear positive or negative intent was detected.'];
    }

    /** @param list<string> $phrases */
    private function contains(string $content, array $phrases): bool
    {
        return collect($phrases)->contains(fn (string $phrase): bool => str_contains($content, $phrase));
    }
}
