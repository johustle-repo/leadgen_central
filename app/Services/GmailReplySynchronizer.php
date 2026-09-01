<?php

namespace App\Services;

use App\EmailReplyClassification;
use App\Models\EmailReply;
use App\Models\EmailSequenceEnrollment;
use App\Models\GmailConnection;
use App\Models\Lead;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class GmailReplySynchronizer
{
    public function __construct(
        private GmailOAuthService $gmail,
        private EmailReplyClassifier $classifier,
        private EmailReplyTextExtractor $replyText,
    ) {}

    public function sync(GmailConnection $connection): int
    {
        $connection->loadMissing('user');
        $accessToken = $this->gmail->accessToken($connection);
        $since = ($connection->last_synced_at?->subMinutes(2) ?? now()->subDay())->timestamp;
        $query = "in:inbox after:{$since}";
        $created = 0;
        $pageToken = null;
        $latestHistoryId = $connection->history_id;

        do {
            $page = $this->gmail->listMessages($accessToken, $query, $pageToken);
            foreach ($page['messages'] ?? [] as $reference) {
                $messageId = (string) ($reference['id'] ?? '');
                if ($messageId === '' || EmailReply::query()->whereBelongsTo($connection, 'gmailConnection')->where('gmail_message_id', $messageId)->exists()) {
                    continue;
                }

                $message = $this->gmail->message($accessToken, $messageId);
                $created += $this->storeMatchedReply($connection, $message);
                $latestHistoryId = (string) ($message['historyId'] ?? $latestHistoryId);
            }
            $pageToken = isset($page['nextPageToken']) ? (string) $page['nextPageToken'] : null;
        } while ($pageToken !== null);

        $connection->update([
            'history_id' => $latestHistoryId,
            'last_synced_at' => now(),
            'status' => 'active',
            'last_error' => null,
        ]);

        return $created;
    }

    /** @param array<string, mixed> $message */
    private function storeMatchedReply(GmailConnection $connection, array $message): int
    {
        $payload = is_array($message['payload'] ?? null) ? $message['payload'] : [];
        $headers = [];
        foreach ($payload['headers'] ?? [] as $header) {
            if (is_array($header)) {
                $headers[mb_strtolower((string) ($header['name'] ?? ''))] = (string) ($header['value'] ?? '');
            }
        }
        [$senderName, $senderEmail] = $this->sender($headers['from'] ?? '');
        if ($senderEmail === null || mb_strtolower($connection->gmail_address) === $senderEmail) {
            return 0;
        }

        $subject = $headers['subject'] ?? '(No subject)';
        $body = Str::limit($this->bodyText($payload), 50000, '');
        $actualReply = $this->replyText->extract($body);
        $classification = $this->classifier->classify($subject, $actualReply, $headers['auto-submitted'] ?? null);
        $lead = $this->matchedLead($connection, $senderEmail, $headers, $body, $classification['classification']);
        if ($lead === null) {
            return 0;
        }

        if ($classification['classification'] === EmailReplyClassification::Bounce && mb_strtolower((string) $lead->email) !== $senderEmail) {
            $senderName = $lead->contact_person ?: $senderName;
            $senderEmail = mb_strtolower((string) $lead->email);
        }

        $internalDate = $message['internalDate'] ?? null;
        $receivedTimestamp = is_numeric($internalDate) ? intdiv((int) $internalDate, 1000) : now()->timestamp;
        $reply = EmailReply::query()->firstOrCreate(
            ['gmail_connection_id' => $connection->id, 'gmail_message_id' => (string) $message['id']],
            [
                'agent_id' => $connection->user_id,
                'lead_id' => $lead->id,
                'gmail_thread_id' => (string) ($message['threadId'] ?? $message['id']),
                'sender_name' => $senderName,
                'sender_email' => $senderEmail,
                'subject' => $subject,
                'body_preview' => Str::limit($actualReply, 500),
                'body_text' => $body,
                'classification' => $classification['classification'],
                'classification_reason' => $classification['reason'],
                'received_at' => Carbon::createFromTimestampUTC($receivedTimestamp),
            ],
        );

        if ($reply->wasRecentlyCreated) {
            EmailSequenceEnrollment::query()
                ->whereBelongsTo($lead)
                ->where('status', 'active')
                ->where('started_at', '<=', $reply->received_at)
                ->update([
                    'status' => 'replied',
                    'next_send_at' => null,
                    'stopped_at' => now(),
                    'stop_reason' => 'Lead replied',
                ]);
        }

        return $reply->wasRecentlyCreated ? 1 : 0;
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function matchedLead(
        GmailConnection $connection,
        string $senderEmail,
        array $headers,
        string $body,
        EmailReplyClassification $classification,
    ): ?Lead {
        $ownedLeads = Lead::query()->whereBelongsTo($connection->user, 'agent');
        $senderLead = (clone $ownedLeads)
            ->whereRaw('LOWER(email) = ?', [$senderEmail])
            ->latest('id')
            ->first();

        if ($senderLead !== null || $classification !== EmailReplyClassification::Bounce) {
            return $senderLead;
        }

        foreach ($this->bounceRecipientEmails($headers, $body) as $recipientEmail) {
            $recipientLead = (clone $ownedLeads)
                ->whereRaw('LOWER(email) = ?', [$recipientEmail])
                ->latest('id')
                ->first();
            if ($recipientLead !== null) {
                return $recipientLead;
            }
        }

        return null;
    }

    /**
     * @param  array<string, string>  $headers
     * @return list<string>
     */
    private function bounceRecipientEmails(array $headers, string $body): array
    {
        $content = implode("\n", array_filter([
            $headers['x-failed-recipients'] ?? null,
            $headers['final-recipient'] ?? null,
            $headers['original-recipient'] ?? null,
            $body,
        ]));

        preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/iu', $content, $matches);

        $emails = array_map(fn (string $email): string => mb_strtolower(trim($email)), $matches[0]);

        return array_values(array_unique(array_filter(
            $emails,
            fn (string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) !== false,
        )));
    }

    /** @return array{0: string|null, 1: string|null} */
    private function sender(string $from): array
    {
        if (preg_match('/^(?:"?([^"<]*)"?\s*)?<([^>]+)>$/', trim($from), $matches) === 1) {
            $email = mb_strtolower(trim($matches[2]));

            return [trim($matches[1]) ?: null, filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null];
        }

        $email = mb_strtolower(trim($from));

        return [null, filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null];
    }

    /** @param array<string, mixed> $payload */
    private function bodyText(array $payload): string
    {
        $mimeType = (string) ($payload['mimeType'] ?? '');
        $data = $payload['body']['data'] ?? null;
        if (is_string($data) && $data !== '') {
            $decoded = $this->decode($data);

            return $mimeType === 'text/html' ? html_entity_decode(strip_tags($decoded)) : $decoded;
        }

        $parts = is_array($payload['parts'] ?? null) ? $payload['parts'] : [];
        foreach (['text/plain', 'text/html'] as $preferredType) {
            foreach ($parts as $part) {
                if (($part['mimeType'] ?? null) === $preferredType) {
                    return $this->bodyText($part);
                }
            }
        }
        foreach ($parts as $part) {
            $body = $this->bodyText($part);
            if ($body !== '') {
                return $body;
            }
        }

        return '';
    }

    private function decode(string $data): string
    {
        $normalized = strtr($data, '-_', '+/');
        $normalized .= str_repeat('=', (4 - strlen($normalized) % 4) % 4);
        $decoded = base64_decode($normalized, true);

        return $decoded === false ? '' : $decoded;
    }
}
