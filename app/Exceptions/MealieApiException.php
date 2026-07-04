<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Thrown when Mealie was reached successfully but returned an error status
 * (4xx/5xx). This is explicitly NOT a connection problem.
 */
class MealieApiException extends RuntimeException
{
    public function __construct(
        public readonly int $statusCode,
        public readonly string $responseBody,
        public readonly string $method,
        public readonly string $endpoint,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            "Mealie API error {$statusCode} on {$method} {$endpoint}: {$responseBody}",
            $statusCode,
            $previous,
        );
    }

    /**
     * A structured, AI-readable explanation. Makes clear this is an error the
     * Mealie application reported (the server was reached), not a network
     * failure, and gives a status-specific hint on how to proceed.
     */
    public function forAi(): string
    {
        $hint = match (true) {
            $this->statusCode === 401 => 'Mealie rejected the API token (401). The token for this connection is invalid or expired. Generate a new token on the Mealie User Profile page; retrying without fixing the token will not help.',
            $this->statusCode === 403 => 'Mealie denied this action due to permissions (403). The token\'s user lacks the required permission for this resource (e.g. household management rights). This is a server-side configuration problem; retrying will not help.',
            $this->statusCode === 404 => 'Mealie could not find the requested record (404). Check that the id or slug exists — recipe ids/slugs and household/cookbook ids are case-sensitive.',
            $this->statusCode === 422 => 'Mealie rejected the submitted data as invalid (422 validation error). Read the field errors in the response body below, correct the input, then try again.',
            $this->statusCode >= 500 => 'Mealie returned an internal server error (5xx). The problem is inside the Mealie application, not the MCP connection.',
            default => "Mealie rejected the request with HTTP {$this->statusCode}.",
        };

        return implode("\n", [
            "Mealie API error: HTTP {$this->statusCode} on {$this->method} {$this->endpoint}.",
            'The MCP server reached Mealie and received this error, so this is NOT a network/connection problem — it is an error reported by the Mealie application itself.',
            $hint,
            'Mealie response body: '.$this->responseBody,
        ]);
    }
}
