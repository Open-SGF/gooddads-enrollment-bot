<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\DropboxOAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;

final readonly class DropboxAuthController
{
    public function __construct(
        private DropboxOAuthService $dropboxOAuthService,
    ) {}

    public function authorize(Request $request): RedirectResponse
    {
        $state = Str::random(40);
        $request->session()->put('dropbox_oauth_state', $state);

        Log::info('Starting Dropbox OAuth authorization redirect.');

        return redirect()->away($this->dropboxOAuthService->buildAuthorizationUrl($state));
    }

    public function callback(Request $request): View
    {
        $expectedState = $request->session()->pull('dropbox_oauth_state');
        $providedState = $request->query('state');

        Log::info('Received Dropbox OAuth callback.');

        if (! is_string($expectedState) || $expectedState === '' || ! is_string($providedState) || ! hash_equals($expectedState, $providedState)) {
            Log::error('Dropbox OAuth callback rejected due to invalid state.', [
                'expected_state_present' => is_string($expectedState) && $expectedState !== '',
                'provided_state_present' => is_string($providedState) && $providedState !== '',
            ]);
            abort(403, 'Invalid Dropbox OAuth state.');
        }

        $error = $request->query('error');
        if (is_string($error) && $error !== '') {
            Log::error('Dropbox OAuth callback returned an error.', ['error' => $error]);
            abort(400, 'Dropbox authorization failed.');
        }

        $code = $request->query('code');
        if (! is_string($code) || $code === '') {
            Log::error('Dropbox OAuth callback missing authorization code.');
            abort(400, 'Missing Dropbox authorization code.');
        }

        $payload = $this->dropboxOAuthService->exchangeAuthorizationCode($code);
        $dropboxToken = $this->dropboxOAuthService->storeAuthorizationTokens($payload);
        $connectedEmail = $this->dropboxOAuthService->fetchAccountEmail($dropboxToken->access_token);

        Log::info('Dropbox OAuth callback completed successfully.', [
            'account_id' => $dropboxToken->account_id,
        ]);

        return view('dropbox.success', [
            'connectedEmail' => $connectedEmail,
        ]);
    }
}
