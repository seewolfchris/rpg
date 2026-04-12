<?php

namespace Tests\Feature;

use Tests\TestCase;

class SecurityDefaultsConfigurationTest extends TestCase
{
    public function test_async_queue_connections_default_to_after_commit_true(): void
    {
        $this->assertTrue((bool) config('queue.connections.database.after_commit'));
        $this->assertTrue((bool) config('queue.connections.redis.after_commit'));
        $this->assertTrue((bool) config('queue.connections.sqs.after_commit'));
        $this->assertTrue((bool) config('queue.connections.beanstalkd.after_commit'));
    }

    public function test_env_example_does_not_ship_insecure_session_cookie_default(): void
    {
        $envExample = (string) file_get_contents(base_path('.env.example'));

        $this->assertStringNotContainsString('SESSION_SECURE_COOKIE=false', $envExample);
    }

    public function test_testing_environment_trusts_proxy_headers_for_security_header_evaluation(): void
    {
        $this->assertSame('*', config('trustedproxy.proxies'));
    }
}
