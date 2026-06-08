<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Campaign;
use App\Models\CampaignGmContactMessage;
use App\Models\CampaignGmContactThread;
use App\Models\CampaignInvitation;
use App\Models\CampaignMembership;
use App\Models\Character;
use App\Models\Handout;
use App\Models\Post;
use App\Models\Scene;
use App\Models\StoryLogEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminUserControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_user_overview(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create([
            'name' => 'User Overview Target',
            'email' => 'overview-target@example.test',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Benutzerverwaltung')
            ->assertSee($target->name)
            ->assertSee($target->email)
            ->assertSee('Benutzer erstellen');
    }

    public function test_non_admin_gets_403_for_user_overview(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.users.index'))
            ->assertForbidden();
    }

    public function test_admin_can_permanently_delete_empty_user(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();

        $this->actingAs($admin)
            ->from(route('admin.users.show', $target))
            ->delete(route('admin.users.destroy', $target))
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseMissing('users', ['id' => $target->id]);
        $this->assertDatabaseMissing('users', ['email' => User::DELETED_USER_SYSTEM_EMAIL]);
    }

    public function test_admin_can_delete_user_with_posts_and_keep_posts_on_system_account(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();
        $campaign = Campaign::factory()->create(['owner_id' => $admin->id]);
        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $admin->id,
        ]);
        $post = Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $target->id,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $target))
            ->assertRedirect(route('admin.users.index'));

        $systemUser = User::query()->where('email', User::DELETED_USER_SYSTEM_EMAIL)->firstOrFail();

        $this->assertDatabaseMissing('users', ['id' => $target->id]);
        $this->assertDatabaseHas('users', [
            'id' => $systemUser->id,
            'name' => User::DELETED_USER_SYSTEM_NAME,
            'status' => UserStatus::SUSPENDED->value,
            'role' => UserRole::PLAYER->value,
            'can_create_campaigns' => false,
            'can_post_without_moderation' => false,
        ]);
        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'user_id' => $systemUser->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertDontSee(User::DELETED_USER_SYSTEM_EMAIL);
    }

    public function test_admin_can_delete_campaign_owner_without_cascade_deleting_campaign(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->gm()->create();
        $campaign = Campaign::factory()->create(['owner_id' => $target->id]);
        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $target->id,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $target))
            ->assertRedirect(route('admin.users.index'));

        $systemUser = User::query()->where('email', User::DELETED_USER_SYSTEM_EMAIL)->firstOrFail();

        $this->assertDatabaseMissing('users', ['id' => $target->id]);
        $this->assertDatabaseHas('campaigns', [
            'id' => $campaign->id,
            'owner_id' => $systemUser->id,
        ]);
        $this->assertDatabaseHas('scenes', [
            'id' => $scene->id,
            'created_by' => $systemUser->id,
        ]);
    }

    public function test_admin_delete_preserves_all_story_content_before_user_delete(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->gm()->create();
        $campaign = Campaign::factory()->create(['owner_id' => $target->id]);
        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $target->id,
        ]);
        $character = Character::factory()->create(['user_id' => $target->id]);
        $post = Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $target->id,
            'character_id' => $character->id,
            'content' => 'Story content must survive user deletion.',
        ]);
        $storyLogEntry = StoryLogEntry::factory()->forScene($scene)->revealed()->create([
            'created_by' => $target->id,
            'body' => 'Chronik bleibt erhalten.',
        ]);
        $handout = Handout::factory()->forScene($scene)->revealed()->create([
            'created_by' => $target->id,
            'description' => 'Handout bleibt erhalten.',
        ]);
        $gmContactThread = CampaignGmContactThread::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $target->id,
            'scene_id' => $scene->id,
            'character_id' => $character->id,
        ]);
        $gmContactMessage = CampaignGmContactMessage::factory()->create([
            'thread_id' => $gmContactThread->id,
            'user_id' => $target->id,
            'content' => 'SL-Beitrag bleibt erhalten.',
        ]);
        $mentionId = DB::table('post_mentions')->insertGetId([
            'post_id' => $post->id,
            'mentioned_user_id' => $target->id,
            'mentioned_character_id' => $character->id,
            'mentioned_character_name' => $character->name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $diceRollId = DB::table('dice_rolls')->insertGetId([
            'scene_id' => $scene->id,
            'post_id' => $post->id,
            'user_id' => $target->id,
            'character_id' => $character->id,
            'roll_mode' => 'normal',
            'modifier' => 0,
            'label' => 'Story roll',
            'rolls' => json_encode([12], JSON_THROW_ON_ERROR),
            'kept_roll' => 12,
            'total' => 12,
            'is_critical_success' => false,
            'is_critical_failure' => false,
            'created_at' => now(),
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $target))
            ->assertRedirect(route('admin.users.index'));

        $systemUser = User::query()->where('email', User::DELETED_USER_SYSTEM_EMAIL)->firstOrFail();

        $this->assertDatabaseMissing('users', ['id' => $target->id]);
        $this->assertDatabaseHas('campaigns', [
            'id' => $campaign->id,
            'owner_id' => $systemUser->id,
        ]);
        $this->assertDatabaseHas('scenes', [
            'id' => $scene->id,
            'campaign_id' => $campaign->id,
            'created_by' => $systemUser->id,
        ]);
        $this->assertDatabaseHas('characters', [
            'id' => $character->id,
            'user_id' => $systemUser->id,
            'name' => $character->name,
        ]);
        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'scene_id' => $scene->id,
            'user_id' => $systemUser->id,
            'character_id' => $character->id,
            'content' => 'Story content must survive user deletion.',
        ]);
        $this->assertDatabaseHas('story_log_entries', [
            'id' => $storyLogEntry->id,
            'campaign_id' => $campaign->id,
            'scene_id' => $scene->id,
            'created_by' => $systemUser->id,
            'body' => 'Chronik bleibt erhalten.',
        ]);
        $this->assertDatabaseHas('handouts', [
            'id' => $handout->id,
            'campaign_id' => $campaign->id,
            'scene_id' => $scene->id,
            'created_by' => $systemUser->id,
            'description' => 'Handout bleibt erhalten.',
        ]);
        $this->assertDatabaseHas('campaign_gm_contact_threads', [
            'id' => $gmContactThread->id,
            'campaign_id' => $campaign->id,
            'created_by' => $systemUser->id,
            'scene_id' => $scene->id,
            'character_id' => $character->id,
        ]);
        $this->assertDatabaseHas('campaign_gm_contact_messages', [
            'id' => $gmContactMessage->id,
            'thread_id' => $gmContactThread->id,
            'user_id' => $systemUser->id,
            'content' => 'SL-Beitrag bleibt erhalten.',
        ]);
        $this->assertDatabaseHas('post_mentions', [
            'id' => $mentionId,
            'post_id' => $post->id,
            'mentioned_user_id' => $systemUser->id,
            'mentioned_character_id' => $character->id,
        ]);
        $this->assertDatabaseHas('dice_rolls', [
            'id' => $diceRollId,
            'scene_id' => $scene->id,
            'post_id' => $post->id,
            'user_id' => $systemUser->id,
            'character_id' => $character->id,
        ]);
    }

    public function test_admin_delete_keeps_characters_and_post_character_links(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();
        $campaign = Campaign::factory()->create(['owner_id' => $admin->id]);
        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $admin->id,
        ]);
        $character = Character::factory()->create(['user_id' => $target->id]);
        $post = Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $target->id,
            'character_id' => $character->id,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $target))
            ->assertRedirect(route('admin.users.index'));

        $systemUser = User::query()->where('email', User::DELETED_USER_SYSTEM_EMAIL)->firstOrFail();

        $this->assertDatabaseMissing('users', ['id' => $target->id]);
        $this->assertDatabaseHas('characters', [
            'id' => $character->id,
            'user_id' => $systemUser->id,
        ]);
        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'user_id' => $systemUser->id,
            'character_id' => $character->id,
        ]);
    }

    public function test_admin_delete_removes_memberships_invitations_sessions_push_subscriptions_and_personal_data(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create(['email' => 'remove-side-data@example.test']);
        $campaign = Campaign::factory()->create(['owner_id' => $admin->id]);
        $acceptedInvitationCampaign = Campaign::factory()->create(['owner_id' => $admin->id]);
        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $admin->id,
        ]);
        $post = Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $admin->id,
        ]);

        CampaignMembership::factory()->create([
            'campaign_id' => $campaign->id,
            'user_id' => $target->id,
        ]);
        CampaignInvitation::query()->create([
            'campaign_id' => $campaign->id,
            'user_id' => $target->id,
            'invited_by' => $admin->id,
            'status' => CampaignInvitation::STATUS_PENDING,
            'role' => CampaignInvitation::ROLE_PLAYER,
            'created_at' => now(),
        ]);
        CampaignInvitation::query()->create([
            'campaign_id' => $acceptedInvitationCampaign->id,
            'user_id' => $target->id,
            'invited_by' => $admin->id,
            'status' => CampaignInvitation::STATUS_ACCEPTED,
            'role' => CampaignInvitation::ROLE_PLAYER,
            'accepted_at' => now(),
            'responded_at' => now(),
            'created_at' => now(),
        ]);

        $now = now();
        DB::table('scene_bookmarks')->insert([
            'user_id' => $target->id,
            'scene_id' => $scene->id,
            'post_id' => $post->id,
            'label' => 'bookmark',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('scene_subscriptions')->insert([
            'scene_id' => $scene->id,
            'user_id' => $target->id,
            'is_muted' => false,
            'last_read_post_id' => $post->id,
            'last_read_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('post_reactions')->insert([
            'post_id' => $post->id,
            'user_id' => $target->id,
            'emoji' => 'like',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('player_notes')->insert([
            'user_id' => $target->id,
            'campaign_id' => $campaign->id,
            'scene_id' => $scene->id,
            'character_id' => null,
            'title' => 'Private note',
            'body' => 'Private body',
            'sort_order' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('sessions')->insert([
            'id' => 'target-session',
            'user_id' => $target->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
            'payload' => 'payload',
            'last_activity' => time(),
        ]);
        DB::table('password_reset_tokens')->insert([
            'email' => $target->email,
            'token' => 'reset-token',
            'created_at' => $now,
        ]);
        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'type' => 'testing',
            'notifiable_type' => User::class,
            'notifiable_id' => $target->id,
            'data' => '{}',
            'read_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('post_scene_notification_deliveries')->insert([
            'post_id' => $post->id,
            'recipient_user_id' => $target->id,
            'channel' => 'database',
            'status' => 'sent',
            'attempt_count' => 1,
            'first_attempted_at' => $now,
            'last_attempted_at' => $now,
            'sent_at' => $now,
            'last_error' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table((string) config('webpush.table_name', 'push_subscriptions'))->insert([
            'subscribable_type' => User::class,
            'subscribable_id' => $target->id,
            'user_id' => $target->id,
            'world_id' => $campaign->world_id,
            'endpoint' => 'https://push.example.test/target',
            'public_key' => 'public',
            'auth_token' => 'auth',
            'content_encoding' => 'aes128gcm',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $target))
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseMissing('users', ['id' => $target->id]);
        $this->assertDatabaseMissing('campaign_memberships', ['user_id' => $target->id]);
        $this->assertDatabaseMissing('campaign_invitations', ['user_id' => $target->id]);
        $this->assertDatabaseMissing('scene_bookmarks', ['user_id' => $target->id]);
        $this->assertDatabaseMissing('scene_subscriptions', ['user_id' => $target->id]);
        $this->assertDatabaseMissing('post_reactions', ['user_id' => $target->id]);
        $this->assertDatabaseMissing('player_notes', ['user_id' => $target->id]);
        $this->assertDatabaseMissing('sessions', ['user_id' => $target->id]);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $target->email]);
        $this->assertDatabaseMissing('notifications', ['notifiable_id' => $target->id]);
        $this->assertDatabaseMissing('post_scene_notification_deliveries', ['recipient_user_id' => $target->id]);
        $this->assertDatabaseMissing((string) config('webpush.table_name', 'push_subscriptions'), ['user_id' => $target->id]);
    }

    public function test_admin_cannot_delete_self(): void
    {
        $admin = User::factory()->admin()->active()->create();
        User::factory()->admin()->active()->create();

        $this->actingAs($admin)
            ->from(route('admin.users.show', $admin))
            ->delete(route('admin.users.destroy', $admin))
            ->assertRedirect(route('admin.users.show', $admin))
            ->assertSessionHasErrors('user');

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_last_admin_cannot_be_deleted(): void
    {
        $admin = User::factory()->admin()->active()->create();

        $this->actingAs($admin)
            ->from(route('admin.users.show', $admin))
            ->delete(route('admin.users.destroy', $admin))
            ->assertRedirect(route('admin.users.show', $admin))
            ->assertSessionHasErrors('user');

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'role' => UserRole::ADMIN->value,
        ]);
    }

    public function test_non_admin_gets_403_for_user_delete(): void
    {
        $user = User::factory()->create();
        $target = User::factory()->create();

        $this->actingAs($user)
            ->delete(route('admin.users.destroy', $target))
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }

    public function test_deleted_user_system_account_cannot_login(): void
    {
        $systemUser = User::factory()->active()->create([
            'name' => User::DELETED_USER_SYSTEM_NAME,
            'email' => User::DELETED_USER_SYSTEM_EMAIL,
            'password' => Hash::make('password'),
        ]);

        $this->post(route('login'), [
            'email' => User::DELETED_USER_SYSTEM_EMAIL,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertTrue($systemUser->refresh()->isDeletedUserSystemAccount());
    }

    public function test_admin_can_create_user_without_false_terms_acceptance(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'name' => 'Admin Created User',
                'email' => 'admin-created@example.test',
                'password' => 'AdminCreatedPassword123!',
                'password_confirmation' => 'AdminCreatedPassword123!',
                'role' => UserRole::PLAYER->value,
                'status' => UserStatus::PENDING->value,
                'can_create_campaigns' => '1',
                'can_post_without_moderation' => '0',
            ])
            ->assertRedirect();

        $created = User::query()->where('email', 'admin-created@example.test')->firstOrFail();

        $this->assertSame('Admin Created User', $created->name);
        $this->assertTrue(Hash::check('AdminCreatedPassword123!', (string) $created->password));
        $this->assertSame(UserRole::PLAYER, $created->role);
        $this->assertSame(UserStatus::PENDING, $created->status);
        $this->assertTrue((bool) $created->can_create_campaigns);
        $this->assertFalse((bool) $created->can_post_without_moderation);
        $this->assertNull($created->terms_accepted_at);
        $this->assertNull($created->terms_version);
    }

    public function test_admin_can_change_name_and_email(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'old-name@example.test',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.users.update', $target), $this->updatePayload($target, [
                'name' => 'New Name',
                'email' => 'new-name@example.test',
            ]))
            ->assertRedirect(route('admin.users.show', $target));

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'name' => 'New Name',
            'email' => 'new-name@example.test',
        ]);
    }

    public function test_admin_can_reset_password(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();

        $this->actingAs($admin)
            ->patch(route('admin.users.update', $target), $this->updatePayload($target, [
                'password' => 'ReplacementPassword123!',
                'password_confirmation' => 'ReplacementPassword123!',
            ]))
            ->assertRedirect(route('admin.users.show', $target));

        $target->refresh();

        $this->assertTrue(Hash::check('ReplacementPassword123!', (string) $target->password));
    }

    public function test_admin_can_change_platform_role(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create([
            'role' => UserRole::PLAYER->value,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.users.update', $target), $this->updatePayload($target, [
                'role' => UserRole::ADMIN->value,
            ]))
            ->assertRedirect(route('admin.users.show', $target));

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'role' => UserRole::ADMIN->value,
        ]);
    }

    public function test_admin_can_change_sl_right(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create([
            'can_create_campaigns' => false,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.users.update', $target), $this->updatePayload($target, [
                'can_create_campaigns' => '1',
            ]))
            ->assertRedirect(route('admin.users.show', $target));

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'can_create_campaigns' => true,
        ]);
    }

    public function test_admin_can_change_moderation_right(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create([
            'can_post_without_moderation' => false,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.users.update', $target), $this->updatePayload($target, [
                'can_post_without_moderation' => '1',
            ]))
            ->assertRedirect(route('admin.users.show', $target));

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'can_post_without_moderation' => true,
        ]);
    }

    public function test_admin_can_change_status(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->active()->create();

        $this->actingAs($admin)
            ->patch(route('admin.users.update', $target), $this->updatePayload($target, [
                'status' => UserStatus::SUSPENDED->value,
                'status_reason' => 'Admin status change',
            ]))
            ->assertRedirect(route('admin.users.show', $target));

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'status' => UserStatus::SUSPENDED->value,
            'suspended_by' => $admin->id,
            'status_reason' => 'Admin status change',
        ]);
    }

    public function test_last_admin_remains_protected(): void
    {
        $admin = User::factory()->admin()->active()->create();

        $this->actingAs($admin)
            ->from(route('admin.users.edit', $admin))
            ->patch(route('admin.users.update', $admin), $this->updatePayload($admin, [
                'role' => UserRole::PLAYER->value,
            ]))
            ->assertRedirect(route('admin.users.edit', $admin))
            ->assertSessionHasErrors('user');

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'role' => UserRole::ADMIN->value,
            'status' => UserStatus::ACTIVE->value,
        ]);
    }

    public function test_admin_cannot_demote_self_even_when_other_admin_exists(): void
    {
        $admin = User::factory()->admin()->active()->create();
        User::factory()->admin()->active()->create();

        $this->actingAs($admin)
            ->from(route('admin.users.edit', $admin))
            ->patch(route('admin.users.update', $admin), $this->updatePayload($admin, [
                'role' => UserRole::PLAYER->value,
            ]))
            ->assertRedirect(route('admin.users.edit', $admin))
            ->assertSessionHasErrors('user');

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'role' => UserRole::ADMIN->value,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function updatePayload(User $user, array $overrides = []): array
    {
        $role = $user->role instanceof UserRole ? $user->role->value : (string) $user->role;
        $status = $user->status instanceof UserStatus ? $user->status->value : (string) $user->status;

        return array_merge([
            'name' => $user->name,
            'email' => $user->email,
            'role' => $role,
            'status' => $status,
            'can_create_campaigns' => $user->can_create_campaigns ? '1' : '0',
            'can_post_without_moderation' => $user->can_post_without_moderation ? '1' : '0',
            'status_reason' => (string) $user->status_reason,
        ], $overrides);
    }
}
