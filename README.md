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

## Запуск через Docker

Docker-вариант поднимает PHP-FPM, Nginx, MySQL, queue worker и scheduler.

Первый запуск локально:

```bash
cp .env.docker.example .env.docker
docker compose --env-file .env.docker build
docker compose --env-file .env.docker run --rm app php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"
```

Скопируйте полученный ключ в `.env.docker`:

```env
APP_KEY=base64:generated_key_here
```

Затем запустите сервис:

```bash
docker compose --env-file .env.docker up -d
```

Открыть в браузере:

```text
http://localhost:8080
```

По умолчанию seeder создаст администратора:

```env
ADMIN_EMAIL="admin@example.com"
ADMIN_PASSWORD="password"
```

Полезные команды:

```bash
docker compose --env-file .env.docker ps
docker compose --env-file .env.docker logs -f app
docker compose --env-file .env.docker logs -f worker
docker compose --env-file .env.docker exec app php artisan amo:bootstrap-account
docker compose --env-file .env.docker exec app php artisan amo:test-connection {accountId}
docker compose --env-file .env.docker exec app php artisan amo:sync-users {accountId}
docker compose --env-file .env.docker down
```

Для полной остановки с удалением MySQL-данных:

```bash
docker compose --env-file .env.docker down -v
```

### Docker на VPS с Nginx и SSL

DNS-запись `develop.sonic.expert` должна указывать на IP VPS. На сервере в `.env.docker` укажите:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://develop.sonic.expert
SESSION_SECURE_COOKIE=true

APP_PORT=127.0.0.1:8080

AMO_EXTERNAL_REDIRECT_URI=https://develop.sonic.expert/amo-oauth/callback
AMO_EXTERNAL_SECRETS_URI=https://develop.sonic.expert/amo-oauth/external/secrets
```

Для первого выпуска SSL-сертификата запустите production stack во временном HTTP-режиме:

```bash
docker compose --env-file .env.docker -f docker-compose.yml -f docker-compose.prod.yml -f docker-compose.ssl-init.yml up -d --build
```

Выпустите сертификат Let's Encrypt:

```bash
docker compose --env-file .env.docker -f docker-compose.yml -f docker-compose.prod.yml -f docker-compose.ssl-init.yml run --rm certbot certonly \
  --webroot \
  -w /var/www/certbot \
  -d develop.sonic.expert \
  --email admin@sonic.expert \
  --agree-tos \
  --no-eff-email
```

После успешного выпуска перезапустите stack в HTTPS-режиме:

```bash
docker compose --env-file .env.docker -f docker-compose.yml -f docker-compose.prod.yml -f docker-compose.ssl-init.yml down
docker compose --env-file .env.docker -f docker-compose.yml -f docker-compose.prod.yml up -d
```

Проверка:

```bash
curl -I https://develop.sonic.expert
docker compose --env-file .env.docker -f docker-compose.yml -f docker-compose.prod.yml ps
```

В production замените дефолтные пароли MySQL в `.env.docker` и храните этот файл вне git. Сервис `certbot` в `docker-compose.prod.yml` будет периодически обновлять сертификат через webroot challenge.

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

## OAuth-подключение без приватной интеграции

Для подключения клиента без ручного создания приватной интеграции используйте страницу:

```text
/amo-oauth/external
```

Сервис создает одноразовое pending-подключение, показывает кнопку amoCRM и принимает:

```text
POST /amo-oauth/external/secrets
GET  /amo-oauth/callback
```

amoCRM отправляет `client_id` и `client_secret` на Secrets URI, затем возвращает пользователя на Redirect URI с `code`, `referer` и `state`. Код обменивается на OAuth-токены через официальную библиотеку `amocrm/amocrm-api-library`, после чего аккаунт и секреты сохраняются в БД.

Для локальной проверки через amoCRM нужен публичный HTTPS URL, например HTTPS-туннель:

```env
APP_URL=https://your-public-tunnel.example
AMO_EXTERNAL_REDIRECT_URI=https://your-public-tunnel.example/amo-oauth/callback
AMO_EXTERNAL_SECRETS_URI=https://your-public-tunnel.example/amo-oauth/external/secrets
AMO_EXTERNAL_INTEGRATION_SCOPES="crm,notifications"
```

Без публичного HTTPS amoCRM не сможет отправить `secrets_uri` и открыть `redirect_uri`.

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
/amo-accounts/{id}/pipelines
/amo-accounts/{id}/crm-audit
/amo-accounts/{id}/integrations
/amo-accounts/{id}/widgets
```

В шапке есть selector клиента. В режиме конкретного клиента dashboard, users audit, воронки, CRM-аудит, интеграции и dashboard-блоки работают в рамках выбранного `amo_account_id`. Dashboard-блоки — это внутренние блоки интерфейса сервиса, а не установленные amoCRM-виджеты клиента.

Модуль воронок использует amoCRM API:

```text
GET  /api/v4/leads/pipelines
POST /api/v4/leads/pipelines
POST /api/v4/leads/pipelines/{pipeline_id}/statuses
```

Создание доступно только admin-пользователям сервиса и требует admin-прав в самом amoCRM аккаунте.

CRM-аудит выгружает:

```text
GET /api/v4/leads/pipelines
GET /api/v4/leads/pipelines/{pipeline_id}/statuses
GET /api/v4/leads/custom_fields
GET /api/v4/contacts/custom_fields
GET /api/v4/companies/custom_fields
GET /api/v4/leads?with=contacts,loss_reason,source
GET /api/v4/contacts?with=leads,companies
GET /api/v4/companies?with=contacts,leads
GET /api/v4/events
GET /api/v4/tasks
GET /api/v4/leads/unsorted
GET /api/v4/leads/loss_reasons
GET /api/v4/sources
GET /api/v4/catalogs
```

Команда:

```bash
php artisan amo:crm-audit {accountId} --from=2026-01-01 --to=2026-05-05
php artisan amo:crm-audit {accountId} --structure-only
```

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
