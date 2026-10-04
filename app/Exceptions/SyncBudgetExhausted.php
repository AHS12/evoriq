<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when a sync job cannot reserve API budget and must be parked until the
 * window resets (SYNC-13). Unlike a generic failure it never burns an attempt —
 * the orchestrator resumes the run at the next reset.
 */
class SyncBudgetExhausted extends RuntimeException
{
    public function __construct(public readonly int $resetsIn)
    {
        parent::__construct('The Clockify API budget window is exhausted.');
    }
}
