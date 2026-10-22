<?php

namespace App\Services;

use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

class DuplicateSubmissionGuard
{
    public function reserve(string $key, int $seconds): bool
    {
        try {
            return Cache::lock('duplicate-submission-lock:'.$key, 10)->block(2, function () use ($key, $seconds): bool {
                if (RateLimiter::tooManyAttempts($key, 1)) {
                    return false;
                }

                RateLimiter::hit($key, $seconds);

                return true;
            });
        } catch (LockTimeoutException) {
            return false;
        }
    }

    public function release(string $key): void
    {
        RateLimiter::clear($key);
    }
}
