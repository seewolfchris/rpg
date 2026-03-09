<?php

namespace Tests\Feature;

use Tests\TestCase;

class RequestIdHeaderTest extends TestCase
{
    public function test_home_response_contains_request_id_header(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertHeader('X-Request-Id');
        $this->assertNotSame('', (string) $response->headers->get('X-Request-Id'));
    }

    public function test_incoming_request_id_is_echoed_back(): void
    {
        $response = $this
            ->withHeader('X-Request-Id', 'req-test-12345')
            ->get('/wissen');

        $response->assertStatus(301);
        $response->assertHeader('X-Request-Id', 'req-test-12345');
    }
}
