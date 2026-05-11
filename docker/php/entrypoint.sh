#!/usr/bin/env sh
set -eu

if [ -n "${DB_HOST:-}" ]; then
    php -r '
        $host = getenv("DB_HOST");
        $port = getenv("DB_PORT") ?: 3306;
        $deadline = time() + 60;

        do {
            $socket = @fsockopen($host, (int) $port, $errno, $errstr, 2);
            if ($socket) {
                fclose($socket);
                exit(0);
            }
            sleep(1);
        } while (time() < $deadline);

        fwrite(STDERR, "Database is not reachable at {$host}:{$port}\n");
        exit(1);
    '
fi

if [ "${APP_RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force
fi

if [ "${APP_RUN_SEEDERS:-false}" = "true" ]; then
    php artisan db:seed --force
fi

if [ "${APP_RUN_AMO_BOOTSTRAP:-false}" = "true" ]; then
    php artisan amo:bootstrap-account
fi

exec "$@"
