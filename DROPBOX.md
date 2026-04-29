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

In your app's `Settings` tab, add a redirect URI that matches how you will access the app. Localhost can use `http`, but any LAN or other non-local callback must use `https`.

- `http://localhost:8080/dropbox/callback`
- `https://<NAS_HOSTNAME>/dropbox/callback`

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
DROPBOX_OAUTH_REQUIRE_BASIC_AUTH=true
DROPBOX_OAUTH_BASIC_USER=dropbox-admin
DROPBOX_OAUTH_BASIC_PASSWORD=change-me
DROPBOX_UPLOAD_PATH=/uploads
```

Security notes:

- `DROPBOX_AUTH_PORT` is published by Docker for host and LAN access.
- Keep `DROPBOX_REDIRECT_URI` aligned with the same host/port as `DROPBOX_AUTH_PORT` and the URL configured in the Dropbox app settings.
- Dropbox rejects non-local `http` redirect URIs. For LAN authorization, use an `https` hostname instead of a raw `http` LAN IP callback.
- Dropbox OAuth routes are protected with HTTP Basic Auth by default using `DROPBOX_OAUTH_BASIC_USER` and `DROPBOX_OAUTH_BASIC_PASSWORD`.

## 5. Authorize The App

1. Start the app: `sail up -d`
2. Open: `http://localhost:8080/dropbox/authorize` from the same machine, or `https://<NAS_HOSTNAME>/dropbox/authorize` from another device on your LAN.

- Your browser will prompt for HTTP Basic Auth credentials before loading the authorize page
- Enter `DROPBOX_OAUTH_BASIC_USER` and `DROPBOX_OAUTH_BASIC_PASSWORD`

3. Sign in and approve Dropbox consent

Note: `DROPBOX_AUTH_PORT` is published by Docker and is not loopback-bound by default, so it can be reached by other LAN clients unless your host firewall/network policy restricts access.

### Basic Auth Setup Notes (Testing vs End User)

- Local testing on one machine: keep `DROPBOX_OAUTH_REQUIRE_BASIC_AUTH=true` and use a local-only credential pair in `.env`.
- LAN/NAS end user flow: share the same credential pair only with trusted admins/operators who perform Dropbox authorization.
- If your reverse proxy already enforces equivalent auth controls, you can explicitly disable app-level guard by setting `DROPBOX_OAUTH_REQUIRE_BASIC_AUTH=false`.
- After changing any Dropbox auth env values, restart the app and clear cached config: `sail artisan config:clear`.

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

Check that Dropbox app settings contain a URI that exactly matches `DROPBOX_REDIRECT_URI` (including host and port)

Examples:

- `http://localhost:8080/dropbox/callback`
- `https://<NAS_HOSTNAME>/dropbox/callback`

### Spinner hang after Dropbox login (embedded browser)

In the VS Code embedded browser, Dropbox may occasionally stall on login during re-authorization. Refresh the page once and continue. This has not reproduced in external browsers (for example Chrome).

## Local HTTPS Testing (Non-Local Callback)

Dropbox rejects non-local `http` redirect URIs. If you need to test an HTTPS callback locally — for example to validate LAN flows before deploying to a NAS — an nginx HTTPS reverse proxy is included in `compose.yaml` and a certificate generation script is provided.

### Setup

1. Install [mkcert](https://github.com/FiloSottile/mkcert) if not already installed: `brew install mkcert`
2. Run the setup script (optionally pass a custom hostname as the first argument):
   ```bash
   bash setup-dropbox-local-https.sh
   # or with a custom hostname:
   bash setup-dropbox-local-https.sh gooddads.localtest.me
   ```
   The script generates a trusted TLS certificate in `docker/nginx/certs/` and prints the `.env` values to use.
3. Update your `.env` with the values printed by the script:
   ```dotenv
   APP_URL=https://gooddads.localtest.me:8443
   DROPBOX_AUTH_HTTPS_PORT=8443
   DROPBOX_REDIRECT_URI=https://gooddads.localtest.me:8443/dropbox/callback
   SESSION_SECURE_COOKIE=true
   ```
4. Add the redirect URI to your Dropbox app settings.
5. Restart Sail: `sail up -d --force-recreate`
6. Open `https://gooddads.localtest.me:8443/dropbox/authorize`

The default hostname `gooddads.localtest.me` works without any DNS configuration because `*.localtest.me` is a public wildcard DNS service that resolves to `127.0.0.1`. The nginx proxy container (`dropbox.auth.proxy`) terminates TLS on port `DROPBOX_AUTH_HTTPS_PORT` and forwards requests to the Laravel app container.

> **Note:** `docker/nginx/certs/` is excluded from git. Re-run the script on each machine that needs HTTPS testing.

## Production NAS (Headless, LAN-Only)

No Laravel code changes are required for this setup. The working fix is to keep the app on its existing internal HTTP port and terminate HTTPS on the NAS or NAS-hosted reverse proxy.

### Implementation Plan

1. Pick one stable LAN hostname for the NAS that resolves from the device where the admin browser runs. Many NAS devices broadcast a Bonjour/mDNS hostname automatically (for example `diskstation.local` or `qnap.local`) — use that if available. Otherwise add an entry in your router's DNS or in `/etc/hosts` on the admin device. Use that hostname consistently for the browser, reverse proxy, app config, and Dropbox redirect URI.
2. Provision a TLS certificate for that hostname and trust it on the admin device that will complete the Dropbox consent flow.
3. Configure the NAS reverse proxy to listen on `https://<NAS_HOSTNAME>` and forward requests to the current app HTTP endpoint and port.
4. Preserve the original host and forwarded protocol headers in that reverse proxy so Laravel sees the canonical HTTPS host.
5. Set `APP_URL=https://<NAS_HOSTNAME>` in the NAS `.env` file.
6. Set `DROPBOX_REDIRECT_URI=https://<NAS_HOSTNAME>/dropbox/callback` in the NAS `.env` file and make the Dropbox app settings use the exact same URI.
7. If the NAS will always be accessed through HTTPS, set `SESSION_SECURE_COOKIE=true`, then restart the app and clear cached config with `sail artisan config:clear` and `sail artisan cache:clear`.
8. Restrict the HTTPS endpoint to trusted LAN or VPN clients only, then complete the validation steps below before treating the setup as production-ready.

### Immediate Test Checklist

- [ ] Open `https://<NAS_HOSTNAME>/dropbox/authorize` from a LAN browser and confirm the app loads without certificate warnings.
- [ ] Complete the Dropbox consent flow and confirm the callback returns to the app successfully.
- [ ] Confirm a token row is created or updated in `dropbox_tokens`.
- [ ] Run `sail artisan dropbox:test-upload` and confirm the probe file is uploaded successfully.
- [ ] Run `sail artisan dropbox:test-upload --expire-token` and confirm the refresh path succeeds.
- [ ] Confirm no `invalid_redirect_uri` error appears during authorization.
- [ ] Confirm the authorization flow still works when you start from the same HTTPS hostname a second time.
- [ ] Confirm Laravel logs show the expected OAuth callback and token storage messages.

### Rollback Checklist

- [ ] Revert `APP_URL` to its previous value.
- [ ] Revert `DROPBOX_REDIRECT_URI` to its previous value.
- [ ] Revert the Dropbox app console redirect URI to the previous setting.
- [ ] Restart the app and clear config cache.
- [ ] Run `sail artisan dropbox:reset-auth --with-sessions` to clear partial state from the failed attempt.
- [ ] Re-run authorization using the rollback URL and confirm uploads work again.

### Production Notes

- Prefer a hostname over a raw IP address. Hostnames are easier to cover with certificates and avoid cookie or callback mismatches.
- Dropbox does not need to contact the NAS directly. The user browser follows the redirect, so LAN-only reachability is acceptable as long as the browser trusts the certificate.
- If the browser reports a certificate trust error, fix certificate trust first. Dropbox redirect URI validation will still fail later if the browser cannot complete the HTTPS callback.

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
