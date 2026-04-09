<?php

declare(strict_types=1);

namespace Xoshbin\Pertuk\Services\Source\Exceptions;

class GitHubRateLimitException extends GitHubRequestException
{
    public function __construct(string $url, string $body)
    {
        parent::__construct(403, $url, $body);
        // Overwrite the message set by parent (PHP 8.5 made getMessage() final).
        $this->message = 'GitHub API rate limit exceeded. Set PERTUK_DOCS_TOKEN to increase the limit. URL: '.$url;
    }
}
