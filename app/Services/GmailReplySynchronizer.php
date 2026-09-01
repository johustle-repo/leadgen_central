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
        $this->reclassifyStoredReplies($connection);
        $since = ($connection->last_synced_at?->subMinutes(2) ?? now()->subDays(7))->timestamp;
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

    private function reclassifyStoredReplies(GmailConnection $connection): void
    {
        EmailReply::query()
            ->whereBelongsTo($connection, 'gmailConnection')
            ->where(function ($query): void {
                $query
                    ->whereNull('classification_reason')
                    ->orWhere('classification_reason', 'not like', 'Updated manually by %');
            })
            ->chunkById(200, function ($replies): void {
                foreach ($replies as $reply) {
                    $actualReply = $this->replyText->extract($reply->body_text ?: $reply->body_preview);
                    $classification = $this->classifier->classify($reply->subject, $actualReply);

                    if ($reply->classification === EmailReplyClassification::AutomaticReply
                        && $classification['classification'] === EmailReplyClassification::NeedsReview) {
                        continue;
                    }

                    if ($reply->classification === $classification['classification']
                        && $reply->classification_reason === $classification['reason']) {
                        continue;
                    }

                    $reply->update([
                        'classification' => $classification['classification'],
                        'classification_reason' => $classification['reason'],
                    ]);
                }
            });
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

        $lead = Lead::query()
            ->whereBelongsTo($connection->user, 'agent')
            ->whereRaw('LOWER(email) = ?', [$senderEmail])
            ->latest('id')
            ->first();
        if ($lead === null) {
            return 0;
        }

        $subject = $headers['subject'] ?? '(No subject)';
        $body = Str::limit($this->bodyText($payload), 50000, '');
        $actualReply = $this->replyText->extract($body);
        $classification = $this->classifier->classify($subject, $actualReply, $headers['auto-submitted'] ?? null);
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
