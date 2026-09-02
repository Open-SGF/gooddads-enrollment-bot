<?php

declare(strict_types=1);

namespace App\Services;

use GuzzleHttp\Exception\ClientException;
use Spatie\Dropbox\RefreshableTokenProvider;

final readonly class DropboxTokenProvider implements RefreshableTokenProvider
{
    public function __construct(
        private DropboxOAuthService $dropboxOAuthService,
    ) {}

    public function getToken(): string
    {
        return $this->dropboxOAuthService->getValidAccessToken();
    }

    public function refresh(ClientException $exception): bool
    {
        if ($exception->getResponse()->getStatusCode() !== 401) {
            return false;
        }

        $this->dropboxOAuthService->getValidAccessToken(forceRefresh: true);

        return true;
    }
}
