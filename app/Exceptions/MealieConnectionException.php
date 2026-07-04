<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Thrown when the MCP server could not reach Mealie at all (DNS, TLS,
 * timeout, refused). This is explicitly a network/connection problem.
 */
class MealieConnectionException extends RuntimeException
{
    public function __construct(
        public readonly string $baseUrl,
        string $detail,
        ?Throwable $previous = null,
    ) {
        parent::__construct("Could not reach Mealie at {$baseUrl}: {$detail}", 0, $previous);
    }

    /**
     * A structured, AI-readable explanation that this is a connection failure,
     * not a permission or data problem.
     */
    public function forAi(): string
    {
        return implode("\n", [
            "Connection error: the MCP server could not reach the Mealie instance at {$this->baseUrl}.",
            'This IS a network/connection problem (DNS, TLS, timeout, or the host is down/unreachable) — it is not a permission or data problem.',
            'Verify MEALIE_BASE_URL and that Mealie is reachable from the MCP server, then try again.',
            'Detail: '.$this->getMessage(),
        ]);
    }
}
