# Repository Guidelines

## Project Structure

Public astronomy reference for the solar system — a Laravel + Livewire front end over the solar-system-db REST API. For astronomy, not astrology.

- `app/` — application classes and services.
- `resources/` — frontend source and templates.
- `routes/` — route definitions.
- `database/` — migrations, factories, and seeders.
- `tests/` — automated tests and fixtures.
- `config/` — configuration definitions.
- `docs/` — design and operational documentation.

## Development and Validation

Use PHP ^8.4, Node.js (use the version pinned by the project/CI). Run commands from the repository root unless the component documentation says otherwise. Configure local dependencies and test services before application tests.

- `composer install` — install PHP dependencies from the lockfile.
- `composer dev` — start the local development processes.
- `composer test` — run the configured test checks.
- `vendor/bin/pint --test` — check PHP formatting.
- `npm ci` — install JavaScript dependencies.
- `npm run dev` — run the configured development entry point.
- `npm run build` — run the configured build.

## Coding and Testing

Follow `.editorconfig` for indentation, encoding, and line endings. Use Laravel Pint for PHP formatting. Tests use Pest. Keep changes focused and follow existing test filenames. Add regression coverage for behavior changes, using isolated fixtures instead of live customer data. For documentation-only edits, verify commands, local links, and `git diff --check`.

## Working Agreement

Read `CONTRIBUTING.md` and `SECURITY.md` before contributing. Honor directory-specific agent instructions. Preserve existing local changes and use a separate branch or worktree when other work is in progress. Keep credentials, private datasets, and generated artifacts out of commits. Deployment, publishing, and live service changes require authorization for that environment.

Use concise commit subjects consistent with recent history (for example, `docs: clarify setup`). Pull requests should explain the change, link relevant issues, and record validation results and any skipped checks. Include screenshots when user-visible behavior changes.

## Licensing

MIT licensed; see [LICENSE](LICENSE). Preserve third-party notices.
