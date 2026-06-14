# Server Migration Checklist

Use this checklist every time the `backend` folder is copied to a new server.

## 1. Required software

- PHP `8.1+` (`8.2` recommended)
- Composer
- MySQL/MariaDB
- Apache or Nginx

## 2. Copy the project

- Copy the full `backend` directory
- Confirm these folders exist after copy:
  - `storage`
  - `storage/logs`
  - `storage/framework/cache`
  - `storage/framework/sessions`
  - `storage/framework/views`
  - `bootstrap/cache`
  - `lang`
  - `lang/km`
  - `lang/en`

## 3. Install PHP dependencies

```bash
composer install --no-dev --optimize-autoloader
```

## 4. Configure `.env`

Minimum required values:

```env
APP_NAME=HRM
APP_ENV=production
APP_DEBUG=false
APP_URL=http://your-server-address/PHDHRM/backend

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=hrmdb
DB_USERNAME=your_db_user
DB_PASSWORD=your_db_password

SESSION_DRIVER=file
CACHE_DRIVER=file
QUEUE_CONNECTION=sync
```

If `.env` is missing:

```bash
copy .env.example .env
php artisan key:generate
```

## 5. Database

- Create database `hrmdb`
- Import the SQL backup
- Or run migrations if this server uses migrations for setup

Test DB connection:

```bash
php artisan migrate:status
```

## 6. Folder permissions

The web server must be able to write to:

- `storage`
- `bootstrap/cache`

This project also reads localization files from:

- `lang/km/language.php`
- `lang/en/language.php`

The latest code now avoids runtime translation writes in production, but those directories should still exist.

## 7. Clear caches after every move

```bash
php artisan optimize:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
```

## 8. Optional storage link

If uploaded files are used:

```bash
php artisan storage:link
```

## 9. Login page smoke test

Open:

- `/PHDHRM/backend/login`

If it fails, immediately inspect:

- `storage/logs/laravel.log`
- Apache error log
- PHP error log

## 10. Most common causes of `/login` failure

- Missing `.env`
- Missing `APP_KEY`
- Wrong DB credentials
- MySQL service not running
- Missing imported database tables
- `storage` or `bootstrap/cache` not writable
- Copied project without `vendor`
- Wrong PHP version or missing PHP extensions

