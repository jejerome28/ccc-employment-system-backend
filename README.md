# CCC Employment System — Backend

> Project for **City College of Calamba**, done as part of IT 210 (Sir Coy).
>
> Group 1: G. Batalla, C. Brosas, A. Erana, J. Goyala

Laravel API (MVC) backing CCC's employment system, MySQL database. The
[`ccc-employment-system-client`](https://github.com/jejerome28/ccc-employment-system-client)
(Next.js) frontend is the only consumer. See [story.md](story.md) for the
product context and [docs/architecture.md](docs/architecture.md) for
conventions.

## Prerequisites

- PHP 8.3+ with `mysqli`/`pdo_mysql` extensions
- [Composer](https://getcomposer.org)
- MySQL 8+, running locally

## Setup

```bash
git clone https://github.com/jejerome28/ccc-employment-system-backend.git
cd ccc-employment-system-backend
composer install
cp .env.example .env
php artisan key:generate
```

Create a local MySQL user/database (adjust user/db name if you already have
one you want to reuse):

```bash
sudo mysql -e "CREATE USER 'ccc_app'@'localhost' IDENTIFIED BY ''; CREATE DATABASE IF NOT EXISTS ccc_employment_system_backend; GRANT ALL PRIVILEGES ON ccc_employment_system_backend.* TO 'ccc_app'@'localhost'; FLUSH PRIVILEGES;"
```

Set `.env` to match (already the default committed in `.env.example`):

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ccc_employment_system_backend
DB_USERNAME=ccc_app
DB_PASSWORD=
```

Run migrations and start the server:

```bash
php artisan migrate
php artisan serve
```

API is at [http://localhost:8000](http://localhost:8000).

## Commands

```bash
php artisan test        # run tests
vendor/bin/pint         # fix code style
vendor/bin/pint --test  # check code style without fixing
```

## Docs

- [story.md](story.md) — what this system is for
- [SECURITY.md](SECURITY.md) — security checklist
- [docs/architecture.md](docs/architecture.md) — stack, MVC pattern, current state
