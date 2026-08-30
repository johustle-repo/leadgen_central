<?php

namespace App\Services;

use App\EmailReplyClassification;

class EmailReplyClassifier
{
    /** @return array{classification: EmailReplyClassification, reason: string} */
    public function classify(string $subject, string $body, ?string $autoSubmitted = null): array
    {
        $content = mb_strtolower("{$subject} {$body}");

        if (($autoSubmitted !== null && mb_strtolower($autoSubmitted) !== 'no') || $this->contains($content, ['out of office', 'automatic reply', 'auto-reply', 'away from the office'])) {
            return ['classification' => EmailReplyClassification::AutomaticReply, 'reason' => 'Detected an automatic or out-of-office response.'];
        }

        if ($this->contains($content, ['not interested', 'remove me', 'unsubscribe', 'do not contact', "don't contact", 'no thanks', 'wrong person', 'stop emailing'])) {
            return ['classification' => EmailReplyClassification::NotLead, 'reason' => 'Detected a decline, opt-out, or wrong-contact response.'];
        }

        if ($this->contains($content, ['interested', 'tell me more', 'send more information', 'more details', 'pricing', 'price list', 'quotation', 'quote', 'schedule a call', 'book a call', 'set up a meeting', 'available for a call'])) {
            return ['classification' => EmailReplyClassification::PossibleLead, 'reason' => 'Detected interest, pricing, or meeting intent.'];
        }

        return ['classification' => EmailReplyClassification::NeedsReview, 'reason' => 'No clear positive or negative intent was detected.'];
    }

    /** @param list<string> $phrases */
    private function contains(string $content, array $phrases): bool
    {
        return collect($phrases)->contains(fn (string $phrase): bool => str_contains($content, $phrase));
    }
}
