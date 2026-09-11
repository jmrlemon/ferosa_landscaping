# Ferosa Landscaping

Ferosa is a Laravel customer and operations system for landscaping services. It includes product shopping, a persistent account cart, checkout and delivery tracking, service scheduling, messaging, notifications, portfolio management, reporting, and an Android AR companion application.

## Local setup

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
php artisan migrate --seed
npm install
npm run build
php artisan serve
```

Start the queue and scheduler in separate terminals when testing notifications and reminders:

```powershell
php artisan queue:work
php artisan schedule:work
```

## Verification

```powershell
php artisan test
npm run build
```

From `../ferosa_mobile`:

```powershell
.\gradlew.bat test
```

## Main workflows

- Customers can browse products, keep a cart across web and Android AR, check out, track delivery, and confirm receipt.
- Customers can estimate work, book an available service slot, receive updates, cancel eligible work, and leave feedback.
- Staff manage services, appointments, messages, products, AR models, and customer updates.
- Administrators additionally manage payments, order workflows, roles, reports, business details, and archived records.

Cart prices, inventory, appointment slots, status transitions, and permissions are enforced by the server. Browser and mobile values are never accepted as authoritative.

See [DEPLOYMENT.md](DEPLOYMENT.md) for production configuration, Android server configuration, queues, scheduling, monitoring, and backups.
