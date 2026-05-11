<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms_accepted' => '1',
        ]);

        $this->assertGuest();
        $response->assertRedirect('/login');
        $response->assertSessionHas('status', 'Account wurde erstellt und wartet auf Freischaltung.');

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'status' => 'pending',
            'terms_version' => '2026-05-testflight',
        ]);
        $this->assertDatabaseMissing('users', [
            'email' => 'test@example.com',
            'terms_accepted_at' => null,
        ]);
    }

    public function test_registration_requires_terms_acceptance(): void
    {
        $response = $this->from('/register')->post('/register', [
            'name' => 'No Terms',
            'email' => 'no-terms@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response
            ->assertRedirect('/register')
            ->assertSessionHasErrors('terms_accepted');

        $this->assertDatabaseMissing('users', [
            'email' => 'no-terms@example.com',
        ]);
    }
}
