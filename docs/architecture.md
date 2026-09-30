# Architecture

## Stack

Laravel 13 (PHP 8.3), MySQL. API-only consumer: `ccc-employment-system-client`
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

API-only. All routes in `routes/api.php`; everything except `POST /api/login`
sits behind `auth:sanctum` (Bearer token). Resources: auth (`/login`, `/logout`,
`/me`), `/dashboard`, `/employees` (+ `/employees/{id}/time-in|time-out`),
`/attendance`. Every response uses the `{success, message, data}` envelope
(`App\Http\Responses\ApiResponse` + renderers in `bootstrap/app.php`).

App timezone is `Asia/Manila` — attendance "today" and clock times are local.
`attendances.work_date` is stored as plain `Y-m-d` (model mutator), times as `H:i:s`.

## Database

MySQL, local dev via user `ccc_app` (no password, local-only). Migrations in
`database/migrations/`; run with `php artisan migrate`.
