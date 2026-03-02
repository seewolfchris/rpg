<?php

namespace Tests\Feature;

use Tests\TestCase;

class PrivacyProtectionTest extends TestCase
{
    public function test_home_response_has_noindex_header_and_meta_tags(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertHeader('X-Robots-Tag');
        $response->assertSee('name="robots"', false);
        $response->assertSee('name="googlebot"', false);
        $response->assertSee('name="bingbot"', false);
    }

    public function test_known_bot_user_agent_is_blocked(): void
    {
        $response = $this
            ->withHeader('User-Agent', 'Mozilla/5.0 (compatible; GPTBot/1.0; +https://openai.com/gptbot)')
            ->get(route('home'));

        $response->assertForbidden();
    }
}
