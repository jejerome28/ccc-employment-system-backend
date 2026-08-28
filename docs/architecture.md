# Architecture

## Stack

Laravel 12 (PHP 8.3), MySQL. API-only consumer: `ccc-employment-system-client`
(Next.js) is the only client, over HTTP/JSON.

## Pattern: MVC

Laravel's native MVC — controllers, Eloquent models, API Resources for
response shaping. Chosen over MVP/MVVM for speed of development (school
project); those patterns fit UI-heavy client apps, not a backend API
framework already built around MVC.

- `app/Models/` — Eloquent models, one per table.
- `app/Http/Controllers/` — thin controllers; validation via Form Requests
  (`app/Http/Requests/`), response shaping via API Resources
  (`app/Http/Resources/`).
- `routes/api.php` — all API routes (create this file if using API-only
  scaffold conventions; not present by default in a non-`--api` install).

## Current state

Default Laravel scaffold. No models, migrations, or routes beyond the
framework defaults yet — this doc describes the intended structure, to
follow as real features get added rather than decided ad hoc per PR.

## Database

MySQL, local dev via user `ccc_app` (no password, local-only). Migrations in
`database/migrations/`; run with `php artisan migrate`.
