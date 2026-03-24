## Project Resources

- [OpenSGF](https://www.opensgf.org/): Join our weekly meetings!
- [Code of Conduct](https://www.opensgf.org/code-of-conduct): Please read before participating
- **Discord**: Click "Join" on [OpenSGF](https://www.opensgf.org/) website
- [Project Documentation](https://docs.opensgf.org/collection/good-dads-0SqBtE9EkS): Product Requirement Documents (PRDs)
- [Project Management](https://plane.sgf.dev/open-sgf/projects/b87b7a4a-10b8-40ee-808d-2ac930c0f46f/issues/): Claim a PRD and/or update PRD status here

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

## Project reset
If you are stuck or Laravel is stuck. 
- **USE WITH CAUTION, THIS IS A HARD RESET OF THE DOCKER CONTAINERS**: `sail down -v --rmi all --remove-orphans`
- Start the Docker containers: `sail up -d`
- Wait for the containers to start up. You can check the status of the containers witj `sail ps`
- Create the database tables: `sail artisan migrate`

## Poll Neon
### Clean-up and log monitoring
- Flush the queue: `sail artisan queue:flush`
- Clear the queue: `sail artisan queue:clear`
- Clear the hash-table and failed jobs table:
    - Start MySQL terminal: `sail artisan tinker`
    - Clear hash-table: `DB::table('neon_participant_hashes')->truncate();`
    - Clear failed jobs-table: `DB::table('failed_jobs')->truncate();`
    - Exit MySQL terminal: `exit`
- Reset the laravel log file (RHEL/Almalinux syntax): `> storage/logs/laravel.log`
- Monitor the laravel log file (RHEL/Almalinux syntax): `tail -f storage/logs/laravel.log`

### Start worker
- Start single-try worker (adjust --tries as needed): `sail artisan queue:work --tries=1`

### Poll Neon
- Poll neon with high verbosity (-vvv): `sail artisan neon:poll-participants -vvv`

