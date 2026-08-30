<?php

namespace App\Services;

use App\Models\GmailConnection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GmailOAuthService
{
    public function authorizationUrl(string $state): string
    {
        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => $this->config('client_id'),
            'redirect_uri' => $this->config('redirect_uri'),
            'response_type' => 'code',
            'scope' => implode(' ', [
                'https://www.googleapis.com/auth/gmail.readonly',
                'https://www.googleapis.com/auth/gmail.send',
            ]),
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'state' => $state,
        ]);
    }

    /** @return array<string, mixed> */
    public function exchangeCode(string $code): array
    {
        return Http::asForm()->acceptJson()->timeout(20)->retry(3, 250)
            ->post('https://oauth2.googleapis.com/token', [
                'client_id' => $this->config('client_id'),
                'client_secret' => $this->config('client_secret'),
                'redirect_uri' => $this->config('redirect_uri'),
                'grant_type' => 'authorization_code',
                'code' => $code,
            ])->throw()->json();
    }

    /** @return array<string, mixed> */
    public function profile(string $accessToken): array
    {
        return $this->get('https://gmail.googleapis.com/gmail/v1/users/me/profile', $accessToken);
    }

    public function accessToken(GmailConnection $connection): string
    {
        if ($connection->token_expires_at?->isAfter(now()->addMinute())) {
            return $connection->access_token;
        }

        $tokens = Http::asForm()->acceptJson()->timeout(20)->retry(3, 250)
            ->post('https://oauth2.googleapis.com/token', [
                'client_id' => $this->config('client_id'),
                'client_secret' => $this->config('client_secret'),
                'grant_type' => 'refresh_token',
                'refresh_token' => $connection->refresh_token,
            ])->throw()->json();

        $connection->update([
            'access_token' => (string) $tokens['access_token'],
            'token_expires_at' => now()->addSeconds((int) ($tokens['expires_in'] ?? 3600)),
            'status' => 'active',
            'last_error' => null,
        ]);

        return $connection->access_token;
    }

    /** @return array<string, mixed> */
    public function listMessages(string $accessToken, string $query, ?string $pageToken = null): array
    {
        return $this->get('https://gmail.googleapis.com/gmail/v1/users/me/messages', $accessToken, array_filter([
            'q' => $query,
            'maxResults' => 100,
            'pageToken' => $pageToken,
        ]));
    }

    /** @return array<string, mixed> */
    public function message(string $accessToken, string $messageId): array
    {
        return $this->get("https://gmail.googleapis.com/gmail/v1/users/me/messages/{$messageId}", $accessToken, ['format' => 'full']);
    }

    /** @return array<string, mixed> */
    public function sendMessage(GmailConnection $connection, string $rawMessage): array
    {
        return Http::withToken($this->accessToken($connection))
            ->acceptJson()
            ->timeout(25)
            ->connectTimeout(5)
            ->post('https://gmail.googleapis.com/gmail/v1/users/me/messages/send', [
                'raw' => $rawMessage,
            ])->throw()->json();
    }

    public function revoke(GmailConnection $connection): void
    {
        Http::asForm()->timeout(15)->retry(2, 200)
            ->post('https://oauth2.googleapis.com/revoke', ['token' => $connection->refresh_token]);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function get(string $url, string $accessToken, array $query = []): array
    {
        return Http::withToken($accessToken)->acceptJson()->timeout(20)->retry(3, 250)
            ->get($url, $query)->throw()->json();
    }

    private function config(string $key): string
    {
        $value = config("services.google.{$key}");
        if (! is_string($value) || $value === '') {
            throw new RuntimeException("Google OAuth configuration [{$key}] is missing.");
        }

        return $value;
    }
}
