<?php

declare(strict_types=1);

namespace Xoshbin\Pertuk\Services\Source\Exceptions;

use RuntimeException;

class GitHubRequestException extends RuntimeException
{
    public function __construct(
        public readonly int $status,
        public readonly string $url,
        string $body,
    ) {
        parent::__construct(
            sprintf('GitHub request to [%s] failed with status %d: %s', $url, $status, $body)
        );
    }
}
