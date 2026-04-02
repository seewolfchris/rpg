<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthUserBoundaryMetaTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_pages_expose_guest_auth_boundary_meta(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee('<meta name="auth-user-id" content="guest">', false);
    }

    public function test_authenticated_pages_expose_user_specific_auth_boundary_meta(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('<meta name="auth-user-id" content="'.$user->id.'">', false);
    }
}

