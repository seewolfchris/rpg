<?php

namespace App\Domain\Post\Contracts;

interface ProbeRollTokenStore
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function issue(array $payload): string;

    /**
     * @return array<string, mixed>|null
     */
    public function read(string $token): ?array;

    /**
     * @param  callable(array<string, mixed>): mixed  $consumer
     */
    public function consume(string $token, callable $consumer): mixed;
}
