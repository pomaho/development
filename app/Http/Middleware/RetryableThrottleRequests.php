<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Throwable;

/**
 * amoCRM sometimes delivers several webhook requests for the same account in the same
 * second. Each one hits RateLimiter::hit() against the same cache row, and MySQL can
 * detect a genuine deadlock between two concurrent INSERT IGNORE/increment attempts on
 * that row regardless of transaction isolation level. Retrying the throttle check (hit()
 * runs before the controller, so nothing downstream has executed yet) is safe and lets
 * the loser of the deadlock just try again instead of returning a 500.
 */
class RetryableThrottleRequests extends ThrottleRequests
{
    // ThrottleRequests::handle() only treats $maxAttempts as a named limiter when it
    // receives exactly 3 arguments (func_num_args() === 3) — forwarding fixed
    // $decayMinutes/$prefix defaults here would break that check, so proxy whatever
    // arguments the route middleware actually passed.
    public function handle($request, Closure $next, ...$args)
    {
        return retry(3, function () use ($request, $next, $args) {
            return parent::handle($request, $next, ...$args);
        }, fn (int $attempt): int => random_int(20, 80) * $attempt, function (Throwable $e): bool {
            return $e instanceof QueryException && $e->getCode() === '40001';
        });
    }
}
