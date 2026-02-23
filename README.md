# 🐾 Pawsitive — Backend API

> Laravel 12 REST API powering the Pawsitive pet e-commerce platform.

---

## Overview

**Pawsitive** is a pet marketplace where users can browse, list, and purchase pets with location-based filtering. This repository contains the **backend API only** — all frontend concerns live in a separate project.

---

## Tech Stack

| Layer | Technology |
|---|---|
| Framework | Laravel 12 (PHP 8.2+) |
| Database | PostgreSQL 18 |
| Auth | Laravel Sanctum |
| Queue | Database driver |
| Cache | Database driver |
| API Prefix | `/api/v1` |

---

## Requirements

- PHP >= 8.2
- Composer
- PostgreSQL 18
- (Optional) Redis for caching/queues in production

---

## Getting Started

### 1. Clone & install dependencies

```bash
git clone <repo-url>
cd backend
composer install
```

### 2. Configure environment

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` and set your database credentials:

```env
APP_NAME=Pawsitive
APP_URL=http://localhost:8000

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=pawsitive
DB_USERNAME=postgres
DB_PASSWORD=your_password
```

### 3. Run migrations & seeders

```bash
php artisan migrate
php artisan db:seed   # optional
```

### 4. Start the development server

```bash
composer dev
# Starts: php artisan serve + queue:listen
```

The API will be available at **`http://localhost:8000/api/v1`**.

---

## API Routes

All routes are prefixed with `/api/v1`.

| Method | Endpoint | Description | Auth |
|---|---|---|---|
| GET | `/api/v1/health` | Health check | No |
| GET | `/api/v1/user` | Get authenticated user | Sanctum |

> More endpoints are added under `routes/api.php` as features are built out. Versioning is handled via the `apiPrefix: 'api/v1'` setting in `bootstrap/app.php`.

---

## Project Structure

```
backend/
├── app/
│   ├── Http/
│   │   └── Controllers/     # API controllers
│   ├── Models/              # Eloquent models
│   └── Providers/
├── bootstrap/
│   └── app.php              # App bootstrap (API-only routing)
├── database/
│   ├── migrations/
│   ├── factories/
│   └── seeders/
├── routes/
│   ├── api.php              # All API routes
│   └── console.php
├── tests/
└── .env.example
```

---

## Running Tests

```bash
composer test
# or
php artisan test
```

---

## Available Composer Scripts

| Script | Command | Description |
|---|---|---|
| `composer dev` | `php artisan serve` + queue | Start local dev environment |
| `composer test` | `php artisan test` | Run test suite |
| `composer setup` | Install, key gen, migrate | First-time full setup |

---

## Security

Report security vulnerabilities privately to the project maintainers. Do **not** open a public issue.

---

## License

MIT
