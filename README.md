# Repo Update

A production-quality WordPress plugin that integrates GitHub repositories with the native WordPress update system for plugins and themes.

## Features

- **Repository management** — Add, edit, delete, enable/disable, and test GitHub connections from the admin UI
- **Branch selection** — Fetch branches from GitHub and select without manual typing
- **Native WordPress updates** — Registers with the core updater so updates appear on Plugins/Themes screens
- **Rollback** — Keeps one previous version before each update; restore or delete from the dashboard
- **Dashboard** — Overview table with versions, status, and quick actions
- **Logging** — Update checks, installs, failures, rollbacks, and GitHub errors
- **Settings** — Global check interval, GitHub timeout, logging toggle, uninstall cleanup

## Requirements

- WordPress 5.8+
- PHP 7.4+ with OpenSSL
- GitHub personal access token (optional for public repositories; required for private repos)

## Installation

1. Download or clone this repository into `wp-content/plugins/repo-update`
2. Activate **Repo Update** in the WordPress admin
3. Go to **Repo Update → Repositories** and add your first GitHub repository

## Configuration

### Add a repository

1. Enter the GitHub **owner** and **repository name**
2. Provide a **personal access token** (stored encrypted; leave blank on edit to keep existing)
3. Choose **Plugin** or **Theme** and select the installed target
4. Click **Fetch Branches** and select a branch
5. Optionally set a per-repository check interval (0 = use global default)
6. Click **Test Connection**, then **Save Repository**

### Update flow

1. Bump the `Version` header in your plugin main file or theme `style.css` on the tracked branch
2. Run **Check now** from the dashboard or wait for the scheduled check
3. Install the update from **Plugins** or **Themes**, or use the dashboard link

### Rollback

When rollback is enabled for a repository, the plugin backs up the current install before updating. Use **Rollback** on the dashboard to restore the previous version, or **Delete backup** to remove the stored copy.

## Changelog

### 1.1.0
- Production hardening: WP_Filesystem, atomic rollback, scoped download auth
- No blocking GitHub calls during update transient builds
- Hourly cron with per-repository due intervals
- Settings API, WP_List_Table admin UI, log retention
- Public repository support without PAT
- GitHub response caching and retries

## Architecture

```
src/
  Admin/        Admin pages and AJAX
  API/          GitHub client (implements ProviderInterface)
  Updater/      WordPress update integration
  Rollback/     Single-version backup manager
  Repository/   Repository model and persistence
  Logger/       Database logging
  Settings/     Global options
  Helpers/      Encryption, slug utilities
  Interfaces/   Extension points for future providers
```

The GitHub client implements `ProviderInterface` so GitLab, Bitbucket, and other providers can be added later without refactoring the updater.

## Security

- Personal access tokens are encrypted at rest using WordPress salts
- All admin actions require `manage_options` and nonces
- Input is sanitized; output is escaped
- Tokens are never logged or exposed in the UI

## Uninstall

If **Delete settings on uninstall** is enabled in Settings, deactivating and deleting the plugin removes options, database tables, logs, rollback copies, and scheduled events.

## Testing

```bash
# Requires Docker
./bin/run-tests.sh

# Or locally with PHP 8.2+
composer install
composer test
```

CI runs unit and smoke tests on every push via GitHub Actions.


## License

GPL v2 or later
