<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RequestIdHeaderTest extends TestCase
{
    use RefreshDatabase;

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

        $response->assertOk();
        $response->assertHeader('X-Request-Id', 'req-test-12345');
    }

    public function test_invalid_incoming_request_id_is_replaced_with_generated_safe_id(): void
    {
        $invalidRequestIds = [
            'short',
            '-invalid-req-id',
            'req!invalid*chars',
            str_repeat('a', 81),
        ];

        foreach ($invalidRequestIds as $incomingRequestId) {
            $response = $this
                ->withHeader('X-Request-Id', $incomingRequestId)
                ->get('/wissen');

            $response->assertOk();
            $response->assertHeader('X-Request-Id');

            $headerValue = (string) $response->headers->get('X-Request-Id');
            $this->assertNotSame($incomingRequestId, $headerValue);
            $this->assertTrue(Str::isUuid($headerValue));
        }
    }
}
