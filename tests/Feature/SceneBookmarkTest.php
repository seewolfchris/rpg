<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Post;
use App\Models\Scene;
use App\Models\SceneBookmark;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SceneBookmarkTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_update_and_delete_scene_bookmark(): void
    {
        $user = User::factory()->create();
        $gm = User::factory()->gm()->create();

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

        $firstPost = Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $gm->id,
        ]);
        $latestPost = Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $gm->id,
        ]);

        $this->actingAs($user)
            ->post(route('campaigns.scenes.bookmark.store', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]), [
                'label' => 'Wichtiger Einstieg',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('scene_bookmarks', [
            'user_id' => $user->id,
            'scene_id' => $scene->id,
            'post_id' => $latestPost->id,
            'label' => 'Wichtiger Einstieg',
        ]);

        $this->actingAs($user)
            ->post(route('campaigns.scenes.bookmark.store', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]), [
                'post_id' => $firstPost->id,
                'label' => 'Ruecksprungpunkt',
            ])
            ->assertRedirect();

        $bookmark = SceneBookmark::query()
            ->where('user_id', $user->id)
            ->where('scene_id', $scene->id)
            ->firstOrFail();

        $this->assertSame($firstPost->id, (int) $bookmark->post_id);
        $this->assertSame('Ruecksprungpunkt', $bookmark->label);

        $this->actingAs($user)
            ->delete(route('campaigns.scenes.bookmark.destroy', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]))
            ->assertRedirect();

        $this->assertDatabaseMissing('scene_bookmarks', [
            'user_id' => $user->id,
            'scene_id' => $scene->id,
        ]);
    }

    public function test_user_cannot_bookmark_post_from_other_scene(): void
    {
        $user = User::factory()->create();
        $gm = User::factory()->gm()->create();

        $campaign = Campaign::factory()->create([
            'owner_id' => $gm->id,
            'status' => 'active',
            'is_public' => true,
        ]);

        $sceneA = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $gm->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);
        $sceneB = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $gm->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);

        $foreignPost = Post::factory()->create([
            'scene_id' => $sceneB->id,
            'user_id' => $gm->id,
        ]);

        $this->actingAs($user)
            ->from(route('campaigns.scenes.show', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $sceneA]))
            ->post(route('campaigns.scenes.bookmark.store', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $sceneA]), [
                'post_id' => $foreignPost->id,
            ])
            ->assertRedirect(route('campaigns.scenes.show', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $sceneA]));

        $this->assertDatabaseMissing('scene_bookmarks', [
            'user_id' => $user->id,
            'scene_id' => $sceneA->id,
        ]);
    }

    public function test_htmx_bookmark_store_returns_thread_item_fragment_from_shared_builder(): void
    {
        $user = User::factory()->create();
        $gm = User::factory()->gm()->create();

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
            'user_id' => $gm->id,
        ]);

        $response = $this->actingAs($user)
            ->withHeaders(['HX-Request' => 'true'])
            ->post(route('campaigns.scenes.bookmark.store', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'scene' => $scene,
            ]), [
                'post_id' => $post->id,
            ]);

        $response->assertOk()
            ->assertViewIs('posts._thread-item')
            ->assertViewHas('post', fn (Post $viewPost): bool => $viewPost->is($post))
            ->assertViewHas('bookmarkCountForNav', 1);
    }

    public function test_bookmark_index_hides_entries_for_inaccessible_private_campaigns(): void
    {
        $user = User::factory()->create();
        $gm = User::factory()->gm()->create();

        $privateCampaign = Campaign::factory()->create([
            'owner_id' => $gm->id,
            'title' => 'Nebelkrone',
            'status' => 'active',
            'is_public' => false,
        ]);

        $scene = Scene::factory()->create([
            'campaign_id' => $privateCampaign->id,
            'created_by' => $gm->id,
            'title' => 'Versiegelte Gruft',
            'status' => 'open',
            'allow_ooc' => true,
        ]);

        SceneBookmark::query()->create([
            'user_id' => $user->id,
            'scene_id' => $scene->id,
            'post_id' => null,
            'label' => 'Sollte unsichtbar sein',
        ]);

        $response = $this->actingAs($user)->get(route('bookmarks.index'));

        $response->assertOk();
        $response->assertDontSee('Versiegelte Gruft');
        $response->assertDontSee('Nebelkrone');
    }

    public function test_bookmark_index_resolves_jump_urls_without_linear_per_bookmark_queries(): void
    {
        $user = User::factory()->create();
        $gm = User::factory()->gm()->create();

        $campaign = Campaign::factory()->create([
            'owner_id' => $gm->id,
            'status' => 'active',
            'is_public' => true,
        ]);

        for ($index = 1; $index <= 12; $index++) {
            $scene = Scene::factory()->create([
                'campaign_id' => $campaign->id,
                'created_by' => $gm->id,
                'title' => 'Bookmark Szene '.$index,
                'status' => 'open',
                'allow_ooc' => true,
            ]);

            $bookmarkPost = Post::factory()->create([
                'scene_id' => $scene->id,
                'user_id' => $gm->id,
            ]);
            Post::factory()->create([
                'scene_id' => $scene->id,
                'user_id' => $gm->id,
            ]);

            SceneBookmark::query()->create([
                'user_id' => $user->id,
                'scene_id' => $scene->id,
                'post_id' => $bookmarkPost->id,
                'label' => 'Marker '.$index,
            ]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAs($user)->get(route('bookmarks.index', ['world' => $campaign->world]));

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $response->assertOk()
            ->assertSee('Bookmark Szene 1')
            ->assertSee('Bookmark Szene 12');

        $this->assertLessThanOrEqual(
            15,
            $queryCount,
            'Bookmark index query count should stay bounded and not scale linearly per bookmark. Actual: '.$queryCount
        );
    }
}
