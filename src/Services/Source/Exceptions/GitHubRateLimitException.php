<?php

declare(strict_types=1);

namespace Xoshbin\Pertuk\Services\Source\Exceptions;

class GitHubRateLimitException extends GitHubRequestException
{
    public function __construct(string $url, string $body)
    {
        parent::__construct(403, $url, $body);
        // We can't override getMessage() (the underlying Throwable::getMessage is final
        // in PHP 8.5+), and we can't pass a different message through parent::__construct()
        // without also changing the $status/$url. So we set $this->message directly after
        // parent construction to swap in the user-friendly rate-limit message.
        $this->message = 'GitHub API rate limit exceeded. Set PERTUK_DOCS_TOKEN to increase the limit. URL: '.$url;
    }
}
