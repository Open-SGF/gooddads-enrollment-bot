## Project Resources

- [OpenSGF](https://www.opensgf.org/): Join our weekly meetings!
- [Code of Conduct](https://www.opensgf.org/code-of-conduct): Please read before participating
- **Discord**: Click "Join" on [OpenSGF](https://www.opensgf.org/) website
- [Project Documentation](https://docs.opensgf.org/collection/good-dads-0SqBtE9EkS): Product Requirement Documents (PRDs)
- [Project Management](https://plane.sgf.dev/open-sgf/projects/b87b7a4a-10b8-40ee-808d-2ac930c0f46f/issues/): Claim a PRD and/or update PRD status here
- [Dropbox App Setup](DROPBOX.md): End-user setup and authorization guide for Dropbox OAuth

## Project Setup

- [Install PHP](https://www.php.net/manual/en/install.php)
- [Install Composer](https://getcomposer.org/doc/00-intro.md)
- Install Docker
  - For Mac: [Docker Desktop](https://docs.docker.com/desktop/install/mac-install/) | [Orbstack](https://docs.orbstack.dev/quick-start#installation)
  - For Windows: [Docker Desktop](https://docs.docker.com/desktop/install/windows-install/)
  - For Linux: [Docker Desktop](https://docs.docker.com/desktop/install/linux-install/)
- Navigate to the project directory and run `composer install`
- Duplicate the .env.example: `cp .env.example .env`
- Configure alias for sail: `alias sail='sh $([ -f sail ] && echo sail || echo vendor/bin/sail)'`
- Start the Docker containers: `sail up -d`
- Wait for the containers to start up. You can check the status of the containers with `sail ps`
- Generate a new APP_KEY: `sail artisan key:generate`. This will automatically update the .env file for the APP_KEY value.
- Create the database tables: `sail artisan migrate`
- Authorize Dropbox uploads by visiting `http://localhost:8080/dropbox/authorize` from the same machine, or your NAS HTTPS hostname for LAN use. `DROPBOX_AUTH_PORT` is published by Docker and reachable on your LAN unless your host firewall/network policy restricts it. Set the Dropbox app callback to the exact URI in `DROPBOX_REDIRECT_URI` and use `https` for any non-local callback.

## Completed form email

Set `MAIL_INTAKE_FORM_RECIPIENTS` to comma-separated email addresses. Leave it empty to disable email.

## PDF version history

Each successfully generated PDF gets a version number starting at 1 for that participant. Dropbox filenames include `_v1_`, `_v2_`, and so on. Notification emails include the version, filename, and changed field names.

The `participants` table stores each participant's Neon ID and one current set of HMAC-SHA256 `field_hashes` as ordinary JSON. Its row also provides the database lock for generation. Each row in `participant_pdf_versions` stores the participant ID, version number, original Neon SHA-256 hash, filename, changed field names, and queue job UUID. Only the participant/version pair is unique; the same hash can appear in multiple versions.

Field changes compare the incoming PDF fields from `ParticipantUpdateData::toPdfArray()`, plus `firstName` and `lastName`, with the participant's saved fingerprints. After successful generation, the new fingerprints replace the previous set in the same transaction that saves the version. Historical versions keep their changed-field lists, not copies of the fingerprints or original field values. The first version is a baseline with an empty changed-field list.

Each fingerprint covers the participant ID, field name, and exact PDF field value encoded as a JSON tuple, preserving distinctions such as null versus an empty string. HMAC uses a dedicated `PARTICIPANT_PDF_HASH_KEY`, separate from `APP_KEY`. There is no decryption operation. Database access alone does not let someone test field-value guesses without the HMAC secret; access to both the database and secret does allow guessing predictable values. This applies to field fingerprints only. Filenames still contain participant names, and queued participant data and Dropbox tokens still use reversible encryption.

Set `PARTICIPANT_PDF_HASH_KEY` to an independently generated secret before generating PDFs. Generate a 32-byte random secret locally with `sail php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'` and store it in your `.env` or secret manager, not in the database or source control. All workers must share the same secret. Generation fails rather than using an empty or short key.

To rotate the HMAC secret, stop polling and workers, set `participants.field_hashes` to SQL `NULL` for all participants, and deploy the new secret to every worker. Rebuild Laravel's configuration cache if enabled before restarting workers and polling. The next generated PDF for each participant establishes a new baseline with no reported field changes, while version numbering and history remain intact. Rotating `APP_KEY` alone does not affect these fingerprints.

Read a participant's history in version order with:

```php
App\Models\ParticipantPdfVersion::query()
    ->where('participant_id', '123')
    ->orderBy('version')
    ->get();
```

Polling and manual fetches with `neon:fetch-by-id` use the same hash comparison. Matching the newest generated version is skipped; `A → B → A` produces versions `1 → 2 → 3`. Queue uniqueness deduplicates pending participant/hash jobs, and workers recheck the latest hash under the participant lock. Pollers and workers must share the same cache store.

Generation failures leave the version history and participant fingerprints unchanged. Delivery happens after generation commits, so missing fields or Dropbox/email failures do not remove a generated version. Retrying the job that generated the latest version creates another PDF version and retries delivery even if the hash is unchanged.

The migration drops the obsolete `neon_participant_hashes` table. Its records cannot reconstruct previous PDFs or field changes, so tracking starts at version 1 without a historical backfill. Before deploying, stop polling and finish pending jobs and resolve failed jobs from the old code, which lack the original Neon hash. Apply the migration before starting new workers and resuming polling.

## Testing and Linting

- Run all checks: `composer test`
- Run formatting and lint checks: `composer test:lint`
- Run static analysis: `composer test:types`
- Run the test suite: `composer test:unit`
- Regenerate IDE helper docs: `composer ide-helper`
- If you are running commands inside Sail, use `sail composer test`, `sail composer test:lint`, `sail composer test:types`, and `sail composer test:unit`

## Project reset

If you are stuck or Laravel is stuck.

- **USE WITH CAUTION, THIS IS A HARD RESET OF THE DOCKER CONTAINERS**: `sail down -v --rmi all --remove-orphans`
- Start the Docker containers: `sail up -d`
- Wait for the containers to start up. You can check the status of the containers witj `sail ps`
- Create the database tables: `sail artisan migrate`
- Regenerate IDE helper docs: `composer ide-helper`

## Poll Neon

### Clean-up and log monitoring

- Run `bash reset-queue.sh` which does the following:
  - Clear configs: `sail artisan config:clear`
  - Clear routes: `sail artisan route:clear`
  - Regenerate autoloader files: `sail composer dump-autoload`
  - Flush the queue: `sail artisan queue:flush`
  - Clear the queue: `sail artisan queue:clear`
  - Clear cache: `sail artisan cache:clear`
  - Clear orphaned database cache locks: `sail artisan cache:clear --locks`
  - Restart the queue: `sail artisan queue:restart`
  - Clear the failed jobs table:
    - Start MySQL terminal: `sail artisan tinker`
    - Clear failed jobs-table: `DB::table('failed_jobs')->truncate();`
    - Exit MySQL terminal: `exit`
  - Reset the laravel log file (RHEL/Almalinux syntax): `> storage/logs/laravel.log`
  - Monitor the laravel log file (RHEL/Almalinux syntax): `tail -f storage/logs/laravel.log`

### Start worker

- Start single-try worker (adjust --tries as needed): `sail artisan queue:work --tries=1`

### Dropbox OAuth

- End-user setup walkthrough: see [DROPBOX.md](DROPBOX.md).
- The OAuth page is exposed on `DROPBOX_AUTH_PORT` and routes to the same Laravel app container, including access from other devices on your LAN.
- The Dropbox app callback should point to `DROPBOX_REDIRECT_URI` and must match the exact host, scheme, and port used by the browser. Use `http` only for `localhost`; use `https` with a LAN hostname for any non-local callback.
- After authorizing successfully, Laravel stores the Dropbox `refresh_token` and rotating `access_token` in the `dropbox_tokens` table.
- Dropbox `access_token` and `refresh_token` are encrypted at rest using Laravel encrypted casts (backed by `APP_KEY`).
- After rotating `APP_KEY`, rewrap existing Dropbox tokens using the previous key: `sail artisan dropbox:rewrap-tokens --from-key="base64:OLD_APP_KEY_VALUE" --force`.
- Reset local Dropbox auth state with `sail artisan dropbox:reset-auth`.
- To also clear local session state during account-switch testing, run `sail artisan dropbox:reset-auth --with-sessions`.
- Validate the integration with `sail artisan dropbox:test-upload`.
- Validate token refresh with `sail artisan dropbox:test-upload --expire-token`.
- Both validation commands generate probe contents in memory and upload them into Dropbox under `DROPBOX_UPLOAD_PATH/dropbox-test/`, without creating a local file.
- Run the automated OAuth tests with `sail artisan test tests/Feature/DropboxOAuthTest.php`.

#### Re-authorization note

If you need to re-authorize (e.g. to rotate tokens or after revoking access in Dropbox), always initiate the flow from your `APP_URL` authorize endpoint (e.g. `http://localhost:8080/dropbox/authorize` for local development, or `https://<NAS_HOSTNAME>/dropbox/authorize` for LAN use) — do not reuse a stale Dropbox URL from a previous attempt.

If you log out of Dropbox first and then start a new flow, the Dropbox login page may appear to hang (spinner animation) after you submit your credentials. This is a known Dropbox SPA behavior: after a successful login, its client-side code tries to restore a cached "entry page" from a prior OAuth session and gets stuck when that cached URL is stale. **Simply refresh the page** — since you are now logged in, Dropbox will re-evaluate the OAuth URL and proceed directly to the consent screen. This only affects re-authorization in the same browser session; it does not affect end-user flows.

### Production image logging

Set `LOG_FORMAT=json` for JSON Laravel stderr, queue, and Nginx access logs, or `text` for plain text. Defaults to `text`; other logs are unchanged.

### Poll Neon

- Participant polling runs every minute while the Laravel scheduler is running. The production Docker image starts the scheduler automatically.
- Start the scheduler locally: `sail artisan schedule:work`.
- Check the configured schedule: `sail artisan schedule:list`.
- Poll neon with high verbosity (-vvv): `sail artisan neon:poll-participants -vvv`
