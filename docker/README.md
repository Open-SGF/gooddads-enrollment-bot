# Docker Layout

- `../Dockerfile` builds the production image from the repository root. It contains only runtime dependencies and starts the scheduler and queue worker under Supervisor.
- `development/` contains the Laravel Sail image. It includes development tools and is built by `compose.yaml`.
- `production/` contains configuration copied into the production image.
- `nginx/` contains the local Dropbox OAuth proxy configuration.
