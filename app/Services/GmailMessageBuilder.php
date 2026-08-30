<?php

namespace App\Services;

use Illuminate\Support\Str;
use RuntimeException;

class GmailMessageBuilder
{
    public function build(
        string $from,
        string $to,
        string $subject,
        string $body,
        ?string $attachmentPath = null,
        ?string $attachmentName = null,
    ): string {
        if ($attachmentPath === null) {
            $lines = [
                "From: {$from}",
                "To: {$to}",
                'Subject: =?UTF-8?B?'.base64_encode($subject).'?=',
                'MIME-Version: 1.0',
                'Content-Type: text/plain; charset=UTF-8',
                'Content-Transfer-Encoding: base64',
                '',
                rtrim(chunk_split(base64_encode($body))),
                '',
            ];

            return rtrim(strtr(base64_encode(implode("\r\n", $lines)), '+/', '-_'), '=');
        }

        $attachment = file_get_contents($attachmentPath);
        if ($attachment === false || $attachmentName === null) {
            throw new RuntimeException('The DUSCAFF brochure could not be read.');
        }

        $boundary = '=_LeadGenCentral_'.Str::random(32);
        $lines = [
            "From: {$from}",
            "To: {$to}",
            'Subject: =?UTF-8?B?'.base64_encode($subject).'?=',
            'MIME-Version: 1.0',
            "Content-Type: multipart/mixed; boundary=\"{$boundary}\"",
            '',
            "--{$boundary}",
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
            '',
            rtrim(chunk_split(base64_encode($body))),
            "--{$boundary}",
            "Content-Type: application/pdf; name=\"{$attachmentName}\"",
            'Content-Transfer-Encoding: base64',
            "Content-Disposition: attachment; filename=\"{$attachmentName}\"",
            '',
            rtrim(chunk_split(base64_encode($attachment))),
            "--{$boundary}--",
            '',
        ];

        return rtrim(strtr(base64_encode(implode("\r\n", $lines)), '+/', '-_'), '=');
    }
}
