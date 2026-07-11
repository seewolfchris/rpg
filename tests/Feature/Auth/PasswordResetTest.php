<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\Auth\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_screen_can_be_rendered(): void
    {
        $response = $this->get(route('password.request'));

        $response->assertStatus(200);
        $response->assertSeeText('Passwort vergessen');
    }

    public function test_reset_link_can_be_requested(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $response = $this->post(route('password.email'), [
            'email' => $user->email,
        ]);

        $response->assertSessionHas('status');
        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_reset_link_uses_configured_app_url_instead_of_request_host(): void
    {
        Notification::fake();
        config(['app.url' => 'https://rpg.example.test']);

        $user = User::factory()->create();

        $this->withHeader('Host', 'attacker.example.test')
            ->post(route('password.email'), [
                'email' => $user->email,
            ])
            ->assertSessionHas('status');

        Notification::assertSentTo(
            $user,
            ResetPasswordNotification::class,
            function (ResetPasswordNotification $notification, array $channels) use ($user): bool {
                $resetUrl = (string) $notification->toMail($user)->viewData['resetUrl'];

                return $channels === ['mail']
                    && str_starts_with($resetUrl, 'https://rpg.example.test/reset-password/')
                    && ! str_contains($resetUrl, 'attacker.example.test');
            },
        );
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $response = $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]));

        $response->assertStatus(200);
        $response->assertSeeText('Neues Passwort setzen');
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $response = $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'neues-passwort-123',
            'password_confirmation' => 'neues-passwort-123',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('login'));

        $this->assertCredentials([
            'email' => $user->email,
            'password' => 'neues-passwort-123',
        ]);
    }

    public function test_password_reset_requests_are_rate_limited(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->post(route('password.email'), [
                'email' => $user->email,
            ])->assertStatus(302);
        }

        $this->post(route('password.email'), [
            'email' => $user->email,
        ])->assertStatus(429);
    }

    public function test_pending_user_can_reset_password_but_cannot_login_until_approved(): void
    {
        $user = User::factory()->pending()->create();
        $token = Password::createToken($user);

        $response = $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'neues-passwort-123',
            'password_confirmation' => 'neues-passwort-123',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('login'));

        $loginResponse = $this->from(route('login'))->post(route('login'), [
            'email' => $user->email,
            'password' => 'neues-passwort-123',
        ]);

        $this->assertGuest();
        $loginResponse
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors([
                'email' => 'Account wartet auf Freischaltung.',
            ]);
    }

    public function test_suspended_user_can_reset_password_but_cannot_login_while_suspended(): void
    {
        $user = User::factory()->suspended()->create();
        $token = Password::createToken($user);

        $response = $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'neues-passwort-123',
            'password_confirmation' => 'neues-passwort-123',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('login'));

        $loginResponse = $this->from(route('login'))->post(route('login'), [
            'email' => $user->email,
            'password' => 'neues-passwort-123',
        ]);

        $this->assertGuest();
        $loginResponse
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors([
                'email' => 'Account ist gesperrt.',
            ]);
    }
}
