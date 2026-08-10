# TransMore Backend

Native PHP backend for TransMore using Composer and PDO.

## Features

- Native PHP API without framework
- MySQL database connection via PDO
- Migration runner with `migrations/*.sql`
- Basic request validation
- Email/password login
- Session management using cookies and database sessions
- `me`, `login`, `logout`, and `migrate` API endpoints

## Setup

1. Copy `.env.example` to `.env`, use a dedicated MySQL user, and configure `FRONTEND_ORIGINS`.
2. Run `composer install` in `backend`.
3. Run migrations from CLI: `php migrate.php`.
4. Start PHP built-in server from the `backend/public` folder:

```bash
php -S localhost:8000
```

## API Endpoints

- `POST /api/migrate` - run migrations (authenticated superadmin only)
- `POST /api/login` - login with JSON body `{ "identifier": "email atau nomor HP", "password": "..." }`; payload lama dengan field `email` tetap didukung
- `POST /api/logout` - clear session cookie
- `GET /api/me` - return authenticated user details

## Notes

- `public/index.php` loads `.env` and sets the session cookie on login.
- The default admin account is seeded by `migrations/002_seed_users.sql`.
- Session records are stored in the `sessions` table and expire after 8 hours.
