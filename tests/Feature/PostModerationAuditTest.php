<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Character;
use App\Models\Post;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostModerationAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_moderation_creates_audit_log_and_notifies_with_reason(): void
    {
        $gm = User::factory()->gm()->create();
        $player = User::factory()->create();

        $campaign = Campaign::factory()->create([
            'owner_id' => $gm->id,
            'status' => 'active',
            'is_public' => true,
        ]);

        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $gm->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);

        $character = Character::factory()->create([
            'user_id' => $player->id,
        ]);

        $post = Post::query()->create([
            'scene_id' => $scene->id,
            'user_id' => $player->id,
            'character_id' => $character->id,
            'post_type' => 'ic',
            'content_format' => 'markdown',
            'content' => 'Die Fackel sinkt in den Nebel.',
            'moderation_status' => 'pending',
        ]);

        $this->actingAs($gm)
            ->patch(route('posts.moderate', $post), [
                'moderation_status' => 'rejected',
                'moderation_note' => 'Bitte mehr Kontext fuer die Szene liefern.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('post_moderation_logs', [
            'post_id' => $post->id,
            'moderator_id' => $gm->id,
            'previous_status' => 'pending',
            'new_status' => 'rejected',
            'reason' => 'Bitte mehr Kontext fuer die Szene liefern.',
        ]);

        $notification = $player->fresh()->unreadNotifications()->first();

        $this->assertNotNull($notification);
        $this->assertSame('post_moderation', $notification->data['kind'] ?? null);
        $this->assertSame('rejected', $notification->data['new_status'] ?? null);
        $this->assertSame('Bitte mehr Kontext fuer die Szene liefern.', $notification->data['moderation_note'] ?? null);

        $sceneResponse = $this->actingAs($gm)->get(route('campaigns.scenes.show', [$campaign, $scene]));
        $sceneResponse->assertOk();
        $sceneResponse->assertSee('Moderationsverlauf');
        $sceneResponse->assertSee('Bitte mehr Kontext fuer die Szene liefern.');
    }

    public function test_moderation_with_same_status_but_reason_still_creates_audit_log(): void
    {
        $gm = User::factory()->gm()->create();
        $player = User::factory()->create();

        $campaign = Campaign::factory()->create([
            'owner_id' => $gm->id,
            'status' => 'active',
            'is_public' => true,
        ]);
        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $gm->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);

        $post = Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $player->id,
            'content' => 'Ich warte auf das Signal.',
            'content_format' => 'plain',
            'post_type' => 'ooc',
            'moderation_status' => 'pending',
        ]);

        $this->actingAs($gm)
            ->patch(route('posts.moderate', $post), [
                'moderation_status' => 'pending',
                'moderation_note' => 'Noch in Abstimmung, bitte warten.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('post_moderation_logs', [
            'post_id' => $post->id,
            'moderator_id' => $gm->id,
            'previous_status' => 'pending',
            'new_status' => 'pending',
            'reason' => 'Noch in Abstimmung, bitte warten.',
        ]);
    }
}
