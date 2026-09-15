# Contributing to Solar

Thanks for taking an interest. This repo is the **front end** for
[sol.wickedsick.com](https://sol.wickedsick.com). All the data comes from
[`solar-system-db`](https://github.com/Wicked-Sick-Ltd/solar-system-db) — if
you've spotted a wrong number on the site, that's almost certainly a data
correction for the backend repo, not a change here. Its
[CONTRIBUTING.md](https://github.com/Wicked-Sick-Ltd/solar-system-db/blob/main/CONTRIBUTING.md)
explains how to propose one with a source.

This is an astronomy site, not astrology. Contributions that add astrology
features will be closed.

## Local setup

You need PHP 8.4+, Composer and Node 20+. [Laravel Herd](https://herd.laravel.com)
is the easiest way to get PHP locally; anything else works too.

```bash
composer install
cp .env.example .env
php artisan key:generate
npm ci
npm run build          # or `npm run dev` for hot reload
```

Point `API_BASE_URL` in `.env` at a backend. The public one works fine for
development:

```
API_BASE_URL=https://api.sol.wickedsick.com/api/v1
```

or run `solar-system-db` locally on `:8003` (see its README) and use
`http://127.0.0.1:8003/api/v1`. Then `php artisan serve` (or open the Herd
site) and you're up. There is no database to migrate.

## Before you open a PR

CI runs exactly these three, so run them locally first:

```bash
./vendor/bin/pint          # formatting (add --test to only check)
./vendor/bin/phpstan analyse
php artisan test           # Pest; tests use Http::fake and never hit the network
```

If you add a route or component, add a smoke test to `tests/Feature/RoutesTest.php`
and, where the page has behaviour, a Livewire test alongside the existing ones.
Fixtures for the fake backend live in `tests/Pest.php`.

## Branches, commits, PRs

- Branch from `main`; open PRs against `main`. `main` deploys to production
  when merged, so keep PRs small and self-contained.
- Use [Conventional Commits](https://www.conventionalcommits.org/):
  `feat(orrery): …`, `fix(seo): …`, `chore(deps): …`.
- Fill in the PR template. Say what you tested and how.
- CI must be green. Dependabot and Laravel Shift PRs are reviewed the same
  way as everyone else's.

## Where things live

- `app/Livewire/` — one full-page Livewire component per route.
- `app/Services/SolarApi/` — the only place that talks to the backend.
  Add new endpoints here as typed methods returning DTOs; never call `Http::`
  from a component.
- `resources/views/components/` — Blade components (`x-prop-row`, `x-badge`,
  `x-api-down`, …). Reuse before inventing.
- `app/Support/Format.php` — all number/unit formatting. Don't format in Blade.

The README's "How it's put together" section has the fuller tour.

## Questions and security

Questions: open an issue, or email hello@wickedsick.com. Security problems:
see [SECURITY.md](SECURITY.md) — please don't file those publicly.
