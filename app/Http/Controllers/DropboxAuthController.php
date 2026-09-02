<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\DropboxOAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Spatie\Dropbox\Client;
use Throwable;

final readonly class DropboxAuthController
{
    public function __construct(
        private DropboxOAuthService $dropboxOAuthService,
        private Client $dropboxClient,
    ) {}

    public function authorize(Request $request): RedirectResponse
    {
        $state = Str::random(40);
        $request->session()->put('dropbox_oauth_state', $state);

        return redirect()->away($this->dropboxOAuthService->buildAuthorizationUrl($state));
    }

    public function callback(Request $request): View
    {
        $expectedState = $request->session()->pull('dropbox_oauth_state');
        $providedState = $request->query('state');

        abort_if(! is_string($expectedState) || $expectedState === '' || ! is_string($providedState) || ! hash_equals($expectedState, $providedState), 403, 'Invalid Dropbox OAuth state.');

        $error = $request->query('error');
        abort_if(is_string($error) && $error !== '', 400, 'Dropbox authorization failed.');

        $code = $request->query('code');
        abort_if(! is_string($code) || $code === '', 400, 'Missing Dropbox authorization code.');

        $dropboxToken = $this->dropboxOAuthService->authorize($code);

        Log::info('Dropbox OAuth callback completed successfully.', [
            'account_id' => $dropboxToken->account_id,
        ]);

        return view('dropbox.success', [
            'connectedEmail' => $this->connectedEmail(),
        ]);
    }

    private function connectedEmail(): ?string
    {
        try {
            $account = $this->dropboxClient->getAccountInfo();
        } catch (Throwable $throwable) {
            Log::warning('Unable to resolve Dropbox account email for success view.', [
                'exception' => $throwable::class,
                'message' => $throwable->getMessage(),
            ]);

            return null;
        }

        $email = $account['email'] ?? null;

        return is_string($email) && $email !== '' ? $email : null;
    }
}
