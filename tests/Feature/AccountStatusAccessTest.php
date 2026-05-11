<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountStatusAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_user_cannot_access_dashboard_and_is_redirected_to_status_page(): void
    {
        $pendingUser = User::factory()->pending()->create();

        $this->actingAs($pendingUser)
            ->get(route('dashboard'))
            ->assertRedirect(route('account.status'));
    }

    public function test_logged_in_user_is_redirected_to_status_page_after_account_is_suspended(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk();

        $user->forceFill([
            'status' => UserStatus::SUSPENDED->value,
            'suspended_at' => now(),
            'suspended_by' => null,
        ])->save();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('account.status'));
    }

    public function test_suspended_admin_cannot_access_admin_user_moderation_ui(): void
    {
        $admin = User::factory()->admin()->suspended()->create();

        $this->actingAs($admin)
            ->get(route('admin.users.moderation.index'))
            ->assertRedirect(route('account.status'));
    }

    public function test_active_admin_can_access_admin_user_moderation_ui(): void
    {
        $admin = User::factory()->admin()->active()->create();

        $this->actingAs($admin)
            ->get(route('admin.users.moderation.index'))
            ->assertOk();
    }

    public function test_non_active_logged_in_user_can_still_logout(): void
    {
        $pendingUser = User::factory()->pending()->create();

        $response = $this->actingAs($pendingUser)->post(route('logout'));

        $response->assertRedirect(route('home'));
        $this->assertGuest();
    }

    public function test_non_active_logged_in_user_can_access_account_status_page(): void
    {
        $suspendedUser = User::factory()->suspended()->create();

        $this->actingAs($suspendedUser)
            ->get(route('account.status'))
            ->assertOk()
            ->assertSeeText('Dein Account ist aktuell gesperrt.');
    }
}
