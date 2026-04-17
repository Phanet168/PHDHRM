# PHDHRM

This repository is organized as a monorepo with separate backend and mobile applications.

## Project Structure

- `backend/` Laravel application (API, web, modules, migrations, resources)
- `mobile/` Flutter application (Android, iOS, web, desktop targets)

## Quick Start

### Backend (Laravel)

1. Go to backend folder.
2. Install dependencies.
3. Configure environment and generate app key.
4. Run migrations.
5. Start local server.

Example commands:

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

### Mobile (Flutter)

1. Go to mobile folder.
2. Install Flutter dependencies.
3. Run on emulator/device.

Example commands:

```bash
cd mobile
flutter pub get
flutter run
```

## Notes

- Backend runtime/generated folders are ignored in git (`vendor`, `node_modules`, `.env`, etc.).
- Mobile generated folders are ignored in git (`.dart_tool`, `build`, `ios/Pods`, `android/.gradle`, etc.).
- VS Code workspace tasks are available in `.vscode/tasks.json`.
