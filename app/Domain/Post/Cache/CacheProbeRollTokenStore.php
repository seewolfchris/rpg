<?php

namespace App\Domain\Post\Cache;

use App\Domain\Post\Contracts\ProbeRollTokenStore;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Repository;

class CacheProbeRollTokenStore implements ProbeRollTokenStore
{
    private const TOKEN_KEY_PREFIX = 'post_probe_roll_preview:';

    private const TOKEN_LOCK_PREFIX = 'post_probe_roll_preview_lock:';

    private const TOKEN_TTL_MINUTES = 30;

    public function __construct(
        private readonly Repository $cache,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function issue(array $payload): string
    {
        $token = bin2hex(random_bytes(32));

        $this->cache->put(
            $this->payloadKey($token),
            $payload,
            now()->addMinutes(self::TOKEN_TTL_MINUTES),
        );

        return $token;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function read(string $token): ?array
    {
        $normalizedToken = $this->normalizeToken($token);

        if ($normalizedToken === null) {
            return null;
        }

        $payload = $this->cache->get($this->payloadKey($normalizedToken));

        return is_array($payload) ? $payload : null;
    }

    /**
     * @param  callable(array<string, mixed>): mixed  $consumer
     */
    public function consume(string $token, callable $consumer): mixed
    {
        $normalizedToken = $this->normalizeToken($token);

        if ($normalizedToken === null) {
            return null;
        }

        $store = $this->cache->getStore();

        if ($store instanceof LockProvider) {
            $lock = $store->lock($this->lockKey($normalizedToken), 5);

            if (! $lock->get()) {
                return null;
            }

            try {
                return $this->consumeUnlocked($normalizedToken, $consumer);
            } finally {
                $lock->release();
            }
        }

        return $this->consumeUnlocked($normalizedToken, $consumer);
    }

    /**
     * @param  callable(array<string, mixed>): mixed  $consumer
     */
    private function consumeUnlocked(string $token, callable $consumer): mixed
    {
        $cacheKey = $this->payloadKey($token);
        $payload = $this->cache->get($cacheKey);

        if (! is_array($payload)) {
            return null;
        }

        $result = $consumer($payload);
        $this->cache->forget($cacheKey);

        return $result;
    }

    private function payloadKey(string $token): string
    {
        return self::TOKEN_KEY_PREFIX.$token;
    }

    private function lockKey(string $token): string
    {
        return self::TOKEN_LOCK_PREFIX.$token;
    }

    private function normalizeToken(string $token): ?string
    {
        $normalizedToken = strtolower(trim($token));

        if ($normalizedToken === '') {
            return null;
        }

        if (preg_match('/\A[a-f0-9]{64}\z/', $normalizedToken) !== 1) {
            return null;
        }

        return $normalizedToken;
    }
}
