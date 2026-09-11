# Ferosa deployment guide

## Required services

- PHP 8.2 or newer with the extensions required by Laravel
- MySQL or MariaDB
- A web server with HTTPS
- A persistent queue worker
- Laravel's scheduler running every minute
- SMTP credentials for customer and staff email
- Public storage linked with `php artisan storage:link`

## First deployment

1. Copy `.env.example` to `.env` and configure production values.
2. Set `APP_ENV=production`, `APP_DEBUG=false`, and an HTTPS `APP_URL`.
3. Install production dependencies with `composer install --no-dev --optimize-autoloader`.
4. Build assets with `npm ci` and `npm run build`.
5. Run `php artisan migrate --force` and `php artisan storage:link`.
6. Cache configuration with `php artisan optimize`.
7. Start a supervised `php artisan queue:work --tries=3` process.
8. Run `php artisan schedule:run` every minute from the operating system scheduler.

## Android builds

Do not edit `Constants.kt` for each server. For a local physical Android device, put the machine-specific URL in the ignored `ferosa_mobile/local.properties` file:

```properties
FEROSA_SERVER_URL=http://YOUR_COMPUTER_LAN_IP/ferosa/ferosa-laravel/public
```

For CI and release builds, pass the server explicitly at build time:

```powershell
.\gradlew.bat assembleRelease -PFEROSA_SERVER_URL=https://your-ferosa-domain.example
```

Release builds reject cleartext HTTP. Development builds default to the Android emulator host and may use HTTP locally.

## Backups

Back up the database, `storage/app/public`, and `storage/app/private` every day. The private directory contains customer payment evidence and must remain access-controlled and encrypted in backup storage. Retain at least seven daily and four weekly copies outside the application server. Perform a restore rehearsal before launch and at least once per quarter. A backup is not considered valid until a restore has succeeded.

## Health and operations

- Monitor `GET /up` for uptime.
- Alert on HTTP 500 responses, queue failures, disk usage, and backup failures.
- Review `storage/logs/laravel.log` and failed queue jobs.
- Run `php artisan test` and `npm run build` before every deployment.
- Run `.\gradlew.bat test` before publishing the Android application.

## Safe release order

1. Put the application in maintenance mode when a migration requires it.
2. Back up the database.
3. Deploy code and built assets.
4. Run migrations.
5. Clear and rebuild caches.
6. Restart queue workers.
7. Verify `/up`, login, cart, checkout, booking, admin updates, and AR product loading.
