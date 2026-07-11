<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CampaignMembershipRole;
use App\Models\Campaign;
use App\Models\CampaignMembership;
use App\Models\Post;
use App\Models\Scene;
use App\Models\SceneSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostModerationVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_post_is_private_until_moderation_approval_publishes_it(): void
    {
        [$owner, $author, $viewer, $campaign, $scene] = $this->seedModeratedScene();

        foreach ([$owner, $viewer] as $subscriber) {
            SceneSubscription::query()->create([
                'scene_id' => $scene->id,
                'user_id' => $subscriber->id,
                'is_muted' => false,
            ]);
        }

        $this->actingAs($author)
            ->post(route('campaigns.scenes.posts.store', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'scene' => $scene,
            ]), [
                'post_type' => 'ooc',
                'content_format' => 'plain',
                'content' => 'VERTRAULICH-BIS-FREIGABE',
            ])
            ->assertRedirect();

        $post = Post::query()->where('scene_id', $scene->id)->latest('id')->firstOrFail();
        $post->update(['is_pinned' => true, 'pinned_at' => now(), 'pinned_by' => $owner->id]);

        $this->assertSame('pending', (string) $post->moderation_status);
        $this->assertSame(0, $viewer->fresh()->unreadNotifications()->count());
        $this->assertSame(0, $owner->fresh()->unreadNotifications()->count());

        $sceneRoute = route('campaigns.scenes.show', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'scene' => $scene,
        ]);

        $this->actingAs($viewer)->get($sceneRoute)
            ->assertOk()
            ->assertDontSeeText('VERTRAULICH-BIS-FREIGABE');
        $this->actingAs($author)->get($sceneRoute)
            ->assertOk()
            ->assertSeeText('VERTRAULICH-BIS-FREIGABE');
        $this->actingAs($owner)->get($sceneRoute)
            ->assertOk()
            ->assertSeeText('VERTRAULICH-BIS-FREIGABE');

        $this->actingAs($owner)
            ->patch(route('posts.moderate', [
                'world' => $campaign->world,
                'post' => $post,
            ]), [
                'moderation_status' => 'approved',
            ])
            ->assertRedirect();

        $this->actingAs($viewer)->get($sceneRoute)
            ->assertOk()
            ->assertSeeText('VERTRAULICH-BIS-FREIGABE');

        $viewerNotification = $viewer->fresh()->unreadNotifications()->first();
        $this->assertNotNull($viewerNotification);
        $this->assertSame('scene_new_post', $viewerNotification->data['kind'] ?? null);
    }

    public function test_rejected_post_remains_visible_only_to_author_and_moderators(): void
    {
        [$owner, $author, $viewer, $campaign, $scene] = $this->seedModeratedScene();

        $post = Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $author->id,
            'post_type' => 'ooc',
            'content_format' => 'plain',
            'content' => 'ABGELEHNTER-VERTRAULICHER-TEXT',
            'moderation_status' => 'rejected',
            'approved_at' => null,
            'approved_by' => null,
        ]);

        $sceneRoute = route('campaigns.scenes.show', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'scene' => $scene,
        ]);

        $this->actingAs($viewer)->get($sceneRoute)
            ->assertOk()
            ->assertDontSeeText('ABGELEHNTER-VERTRAULICHER-TEXT');
        $this->actingAs($author)->get($sceneRoute)
            ->assertOk()
            ->assertSeeText('ABGELEHNTER-VERTRAULICHER-TEXT');
        $this->actingAs($owner)->get($sceneRoute)
            ->assertOk()
            ->assertSeeText('ABGELEHNTER-VERTRAULICHER-TEXT');

        $this->assertFalse($viewer->can('view', $post));
        $this->assertTrue($author->can('view', $post));
        $this->assertTrue($owner->can('view', $post));
    }

    public function test_author_cannot_update_or_delete_post_in_archived_scene(): void
    {
        [$owner, $author, , $campaign, $scene] = $this->seedModeratedScene();
        $scene->update(['status' => 'archived']);

        $post = Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $author->id,
            'post_type' => 'ooc',
            'content_format' => 'plain',
            'content' => 'UNVERAENDERLICHES-ARCHIV',
            'moderation_status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $owner->id,
        ]);

        $this->actingAs($author)
            ->patch(route('posts.update', ['world' => $campaign->world, 'post' => $post]), [
                'post_type' => 'ooc',
                'content_format' => 'plain',
                'content' => 'Manipuliert',
            ])
            ->assertForbidden();
        $this->actingAs($author)
            ->delete(route('posts.destroy', ['world' => $campaign->world, 'post' => $post]))
            ->assertForbidden();

        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'content' => 'UNVERAENDERLICHES-ARCHIV',
            'deleted_at' => null,
        ]);
    }

    /**
     * @return array{User, User, User, Campaign, Scene}
     */
    private function seedModeratedScene(): array
    {
        $owner = User::factory()->gm()->create();
        $author = User::factory()->create();
        $viewer = User::factory()->create();
        $campaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'status' => 'active',
            'is_public' => true,
            'requires_post_moderation' => true,
        ]);

        foreach ([$author, $viewer] as $member) {
            CampaignMembership::query()->create([
                'campaign_id' => $campaign->id,
                'user_id' => $member->id,
                'role' => CampaignMembershipRole::PLAYER->value,
                'assigned_by' => $owner->id,
                'assigned_at' => now(),
            ]);
        }

        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $owner->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);

        return [$owner, $author, $viewer, $campaign, $scene];
    }
}
