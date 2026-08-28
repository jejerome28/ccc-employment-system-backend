# Security Policy — CCC Employment System (Backend)

This is the Laravel API backing CCC's employment system (see
[story.md](story.md)). It owns the MySQL database — all employee/HR data
lives here, and the `ccc-employment-system-client` frontend depends on this
API to enforce authorization correctly on every request.

This is a fresh scaffold: no auth, no models/migrations beyond Laravel's
defaults yet. This document is a checklist for what to get right as those
get built.

## Reporting a vulnerability

Report internally to the engineering team lead — do not open a public GitHub
issue for a security finding, since this handles employee/HR data.

## Things to get right when building this out

- **Auth**: use Laravel Sanctum/Passport for API auth, not a hand-rolled
  token scheme. Every route that returns employee data must resolve the
  authenticated user and authorize against it — a client-supplied employee ID
  is never itself proof of authorization.
- **Mass assignment**: every Eloquent model needs an explicit `$fillable` (or
  `$guarded`) — never leave a model wide open to mass assignment from request
  input.
- **Validation**: validate all input via Form Request classes, not inline in
  controllers — the API is the actual trust boundary; never trust client-side
  validation alone.
- **PII**: employee records are personal data — never log full request/response
  bodies containing them; log identifiers only. Don't return more fields than
  the endpoint's consumer needs (use API Resources, not raw model dumps).
- **Mass DB credentials**: `.env` (gitignored) holds the DB password; never
  commit real credentials, never hardcode them in `config/database.php`.
- **CORS**: restrict `config/cors.php` to the actual frontend origin(s) once
  known, not `*`.

## Pre-Deploy Security Checklist

- [ ] `php artisan test` passes.
- [ ] `vendor/bin/pint --test` is clean.
- [ ] `composer audit` has no unresolved high/critical findings.
- [ ] Every new Eloquent model has explicit `$fillable`/`$guarded`.
- [ ] Every new route that returns employee data authorizes against the
      authenticated user, not a client-supplied ID alone.
- [ ] `.env` is not committed; no real secret ever hardcoded in `config/`.
