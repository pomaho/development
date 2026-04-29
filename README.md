# amo Integrator Hub

Laravel-сервис для интегратора amoCRM: multi-account подключения, проверка API, синхронизация пользователей/ролей, аудит прав, API-логи и расширяемый dashboard.

## Локальный запуск

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan amo:bootstrap-account
php artisan serve
```

В отдельном терминале:

```bash
npm install
npm run dev
```

Первый администратор создается seeder-ом из env:

```env
ADMIN_NAME="Admin"
ADMIN_EMAIL="admin@example.com"
ADMIN_PASSWORD="password"
```

Публичная регистрация выключена:

```env
ENABLE_REGISTRATION=false
```

## amoCRM bootstrap

Env используется только для первого локального подключения. Основное хранение аккаунтов и секретов идет в БД.

```env
AMO_BOOTSTRAP_ENABLED=true
AMO_BOOTSTRAP_NAME="Первый клиент"
AMO_BOOTSTRAP_BASE_DOMAIN=company.amocrm.ru
AMO_BOOTSTRAP_ACCESS_TOKEN=token_here
AMO_BOOTSTRAP_TOKEN_TYPE=long_lived
```

Команды:

```bash
php artisan amo:bootstrap-account
php artisan amo:test-connection {accountId}
php artisan amo:sync-users {accountId}
php artisan amo:sync-users
php artisan amo:refresh-tokens
```

## Архитектура

- Каждый клиент amoCRM хранится в `amo_accounts`.
- Секреты хранятся в `amo_credentials` через Laravel encrypted casts.
- Все данные amoCRM связаны с `amo_account_id`.
- Контроллеры не обращаются к amoCRM напрямую.
- Основной слой amoCRM находится в `app/Services/Amo`.
- Официальная библиотека установлена как основная зависимость: `amocrm/amocrm-api-library`.
- Fallback HTTP-клиент изолирован в `AmoFallbackHttpClient`, использует тот же `AmoTokenManager` и пишет безопасные записи в `api_request_logs`.

Клиентский контекст в веб-интерфейсе:

```text
/dashboard
/amo-accounts/{id}/dashboard
/amo-accounts/{id}/users
/amo-accounts/{id}/roles
/amo-accounts/{id}/integrations
/amo-accounts/{id}/widgets
```

В шапке есть selector клиента. В режиме конкретного клиента dashboard, users audit, интеграции и dashboard-блоки работают в рамках выбранного `amo_account_id`. Dashboard-блоки — это внутренние блоки интерфейса сервиса, а не установленные amoCRM-виджеты клиента.

## Production

Пример важных env-настроек:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.ru
DB_CONNECTION=mysql
QUEUE_CONNECTION=database
SESSION_SECURE_COOKIE=true
API_LOG_RETENTION_DAYS=30
```

На сервере нужно настроить HTTPS, MySQL или PostgreSQL, backup БД, безопасный backup `.env`, ротацию логов и мониторинг ошибок.

Cron для Scheduler:

```cron
* * * * * php /path/to/project/artisan schedule:run >> /dev/null 2>&1
```

Supervisor для очередей:

```bash
php artisan queue:work --tries=3 --timeout=120
```

`.env` не коммитить. В production держать `APP_DEBUG=false`.
