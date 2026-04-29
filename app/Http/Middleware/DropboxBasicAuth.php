<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

final class DropboxBasicAuth
{
    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) config('services.dropbox.require_basic_auth', true)) {
            return $next($request);
        }

        $expectedUsernameConfig = config('services.dropbox.basic_auth_user', '');
        $expectedPasswordConfig = config('services.dropbox.basic_auth_password', '');
        $expectedUsername = is_string($expectedUsernameConfig) ? $expectedUsernameConfig : '';
        $expectedPassword = is_string($expectedPasswordConfig) ? $expectedPasswordConfig : '';

        if ($expectedUsername === '' || $expectedPassword === '') {
            Log::error('Dropbox OAuth basic auth is enabled but credentials are not configured.');
            abort(500, 'Dropbox OAuth basic auth credentials are not configured.');
        }

        $providedUsername = $request->getUser() ?? '';
        $providedPassword = $request->getPassword() ?? '';

        if (
            hash_equals($expectedUsername, $providedUsername)
            && hash_equals($expectedPassword, $providedPassword)
        ) {
            return $next($request);
        }

        return response('Unauthorized', 401, [
            'WWW-Authenticate' => 'Basic realm="Dropbox OAuth"',
        ]);
    }
}
