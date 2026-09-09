<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Google rejected a refresh_token exchange with invalid_grant: the mailbox's
 * offline access was revoked, expired, or the connected Google account's
 * password/security settings changed. No amount of retrying fixes this -
 * the agent (or an administrator on their behalf) must reconnect the
 * mailbox through the OAuth consent screen again.
 */
class GmailReauthorizationRequiredException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Gmail access was revoked or expired. Reconnect this Gmail account to resume syncing.');
    }
}
