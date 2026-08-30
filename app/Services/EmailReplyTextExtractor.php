<?php

namespace App\Services;

class EmailReplyTextExtractor
{
    public function extract(string $message): string
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $message);
        $cutAt = strlen($normalized);

        foreach ([
            '/^\s*On .+wrote:\s*$/miu',
            '/^\s*-{2,}\s*Original Message\s*-{2,}\s*$/miu',
            '/^\s*From:\s.+$/miu',
            '/^\s*>/mu',
        ] as $pattern) {
            if (preg_match($pattern, $normalized, $matches, PREG_OFFSET_CAPTURE) === 1) {
                $cutAt = min($cutAt, $matches[0][1]);
            }
        }

        $reply = trim(substr($normalized, 0, $cutAt));

        return $reply !== '' ? $reply : trim($normalized);
    }
}
