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
- Authorize Dropbox uploads by visiting `http://localhost:8080/dropbox/authorize` after the app is running. Set the Dropbox app callback to `http://localhost:8080/dropbox/callback`.

## Project reset

If you are stuck or Laravel is stuck.

- **USE WITH CAUTION, THIS IS A HARD RESET OF THE DOCKER CONTAINERS**: `sail down -v --rmi all --remove-orphans`
- Start the Docker containers: `sail up -d`
- Wait for the containers to start up. You can check the status of the containers witj `sail ps`
- Create the database tables: `sail artisan migrate`

## Poll Neon

### Clean-up and log monitoring

- Run `bash reset-queue.sh` which does the following:
  - Clear configs: `sail artisan config:clear`
  - Clear cache: `sail artisan cache:clear`
  - Clear routes: `sail artisan route:clear`
  - Regenerate autoloader files: `sail composer dump-autoload`
  - Flush the queue: `sail artisan queue:flush`
  - Clear the queue: `sail artisan queue:clear`
  - Restart the queue: `sail artisan queue:restart`
  - Clear the hash-table and failed jobs table:
    - Start MySQL terminal: `sail artisan tinker`
    - Clear hash-table: `DB::table('neon_participant_hashes')->truncate();`
    - Clear failed jobs-table: `DB::table('failed_jobs')->truncate();`
    - Exit MySQL terminal: `exit`
  - Reset the laravel log file (RHEL/Almalinux syntax): `> storage/logs/laravel.log`
  - Monitor the laravel log file (RHEL/Almalinux syntax): `tail -f storage/logs/laravel.log`

### Start worker

- Start single-try worker (adjust --tries as needed): `sail artisan queue:work --tries=1`

### Dropbox OAuth

- End-user setup walkthrough: see [DROPBOX.md](DROPBOX.md).
- The OAuth page is exposed on `DROPBOX_AUTH_PORT` and routes to the same Laravel app container.
- The Dropbox app callback should point to `http://localhost:8080/dropbox/callback` unless you change `DROPBOX_REDIRECT_URI`.
- After authorizing successfully, Laravel stores the Dropbox `refresh_token` and rotating `access_token` in the `dropbox_tokens` table.
- Dropbox `access_token` and `refresh_token` are encrypted at rest using Laravel encrypted casts (backed by `APP_KEY`).
- After rotating `APP_KEY`, rewrap existing Dropbox tokens using the previous key: `sail artisan dropbox:rewrap-tokens --from-key="base64:OLD_APP_KEY_VALUE" --force`.
- Reset local Dropbox auth state with `sail artisan dropbox:reset-auth`.
- To also clear local session state during account-switch testing, run `sail artisan dropbox:reset-auth --with-sessions`.
- Validate the integration with `sail artisan dropbox:test-upload`.
- Validate token refresh with `sail artisan dropbox:test-upload --expire-token`.
- Both validation commands upload a probe file into Dropbox under `DROPBOX_UPLOAD_PATH/dropbox-test/` and remove their temporary local probe file afterward.
- Run the automated OAuth tests with `sail artisan test tests/Feature/DropboxOAuthTest.php`.

#### Re-authorization note

If you need to re-authorize (e.g. to rotate tokens or after revoking access in Dropbox), always initiate the flow from `http://localhost:8080/dropbox/authorize` — do not reuse a stale Dropbox URL from a previous attempt.

If you log out of Dropbox first and then start a new flow, the Dropbox login page may appear to hang (spinner animation) after you submit your credentials. This is a known Dropbox SPA behavior: after a successful login, its client-side code tries to restore a cached "entry page" from a prior OAuth session and gets stuck when that cached URL is stale. **Simply refresh the page** — since you are now logged in, Dropbox will re-evaluate the OAuth URL and proceed directly to the consent screen. This only affects re-authorization in the same browser session; it does not affect end-user flows.

### Poll Neon

- Poll neon with high verbosity (-vvv): `sail artisan neon:poll-participants -vvv`
