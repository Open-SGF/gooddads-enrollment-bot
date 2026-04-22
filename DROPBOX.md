# Dropbox App Setup

This guide explains how to configure Dropbox for this project and complete first-time authorization.

## 1. Create The Dropbox App

1. Open https://www.dropbox.com/developers/apps
2. Select `Create app`
3. Choose:
   - `Scoped access`
   - `App folder` (recommended)
4. Enter a unique app name and create the app

## 2. Configure Permissions

In your app's `Permissions` tab, enable:

- `files.content.write`
- `files.metadata.read`
- `account_info.read`

Save changes.

## 3. Configure OAuth Redirect URI

In your app's `Settings` tab, add this redirect URI exactly:

- `http://localhost:8080/dropbox/callback`

Important:

- It must match exactly
- Do not use a trailing slash
- Do not use `auth.php`

## 4. Add Credentials To Local Environment

Set these values in your `.env` file:

```dotenv
DROPBOX_AUTH_PORT=8080
DROPBOX_APP_KEY=your_dropbox_app_key
DROPBOX_APP_SECRET=your_dropbox_app_secret
DROPBOX_REDIRECT_URI=http://localhost:8080/dropbox/callback
DROPBOX_UPLOAD_PATH=/uploads
```

Security notes:

- `DROPBOX_AUTH_PORT` is intended for local use only and is bound to `127.0.0.1` in `compose.yaml`.
- Keep `DROPBOX_REDIRECT_URI` aligned with the same host/port as `DROPBOX_AUTH_PORT`.

## 5. Authorize The App

1. Start the app: `sail up -d`
2. Open: `http://localhost:8080/dropbox/authorize`
3. Sign in and approve Dropbox consent

After success, the app stores refresh/access tokens in `dropbox_tokens` and can upload files.

Security note:

- `access_token` and `refresh_token` are encrypted at rest using Laravel encryption.
- Encryption/decryption depends on your `APP_KEY`. Keep it stable and protected.

If you rotate `APP_KEY`, rewrap existing Dropbox tokens using the old key:

- `sail artisan dropbox:rewrap-tokens --from-key="base64:OLD_APP_KEY_VALUE" --force`

If you no longer have the old key, re-authorization is required because existing encrypted tokens cannot be decrypted.

## 6. Reset Auth State For Retesting

Use:

- `sail artisan dropbox:reset-auth`

For a fully clean local retest (including sessions):

- `sail artisan dropbox:reset-auth --with-sessions`

## Troubleshooting

### invalid_redirect_uri

Check that Dropbox app settings contain exactly:

- `http://localhost:8080/dropbox/callback`

### Spinner hang after Dropbox login (embedded browser)

In the VS Code embedded browser, Dropbox may occasionally stall on login during re-authorization. Refresh the page once and continue. This has not reproduced in external browsers (for example Chrome).

## Observability Quick Reference

When diagnosing OAuth or upload problems, monitor Laravel logs:

- `tail -f storage/logs/laravel.log`

Useful messages and what they mean:

- `Starting Dropbox OAuth authorization redirect.`
  - OAuth flow started from `/dropbox/authorize`.
- `Received Dropbox OAuth callback.`
  - Dropbox redirected back to your app.
- `Dropbox OAuth callback rejected due to invalid state.`
  - OAuth CSRF/state mismatch; restart at `/dropbox/authorize`.
- `429 Too Many Requests` from `/dropbox/callback`
  - OAuth callback route throttling was triggered; wait a minute and retry the flow once.
- `Dropbox OAuth callback returned an error.`
  - Dropbox returned an explicit OAuth error (for example denied consent).
- `Dropbox OAuth authorization code exchange failed.`
  - Token exchange with Dropbox failed; inspect `http_code` and `error_summary` in log context.
- `Stored Dropbox OAuth tokens.`
  - Refresh/access tokens were persisted successfully.
- `Refreshing Dropbox access token.` and `Dropbox access token refresh succeeded.`
  - Token refresh path was used and completed.
- `Dropbox upload failed: API error` or `Dropbox upload curl request failed.`
  - Upload failed at Dropbox API/curl level; inspect attached log context.
- `Dropbox upload succeeded`
  - Upload completed successfully.
