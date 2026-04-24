<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\DiceRoll;
use App\Models\Post;
use App\Models\PostModerationLog;
use App\Models\PostReaction;
use App\Models\PostRevision;
use App\Models\Scene;
use App\Models\SceneBookmark;
use App\Models\SceneSubscription;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostTombstoneThreadTest extends TestCase
{
    use RefreshDatabase;

    public function test_thread_renders_soft_deleted_post_as_non_leaking_tombstone_in_original_position(): void
    {
        config(['features.wave4.reactions' => true]);

        [$campaign, $scene, $gm] = $this->seedCampaignAndScene();
        $author = User::factory()->create(['name' => 'Tombstone Author']);
        $deleter = User::factory()->create(['name' => 'Secret Deleter']);
        $viewer = User::factory()->create();

        $oldestPost = Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $gm->id,
            'post_type' => 'ic',
            'content_format' => 'plain',
            'content' => 'OLDER_LIVE_POST_CONTENT',
            'moderation_status' => 'approved',
        ]);
        $deletedPost = Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $author->id,
            'post_type' => 'ic',
            'content_format' => 'plain',
            'content' => 'TOMBSTONE_ORIGINAL_SECRET_CONTENT',
            'meta' => [
                'ic_quote' => 'TOMBSTONE_IC_QUOTE_SECRET',
                'inventory_award' => [
                    'character_name' => 'TOMBSTONE_INVENTORY_CHARACTER',
                    'item' => 'TOMBSTONE_INVENTORY_ITEM',
                    'quantity' => 1,
                    'equipped' => true,
                ],
            ],
            'moderation_status' => 'approved',
        ]);
        $newestPost = Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $gm->id,
            'post_type' => 'ic',
            'content_format' => 'plain',
            'content' => 'NEWER_LIVE_POST_CONTENT',
            'moderation_status' => 'approved',
        ]);

        PostRevision::query()->create([
            'post_id' => $deletedPost->id,
            'version' => 1,
            'editor_id' => $author->id,
            'character_id' => null,
            'post_type' => 'ic',
            'content_format' => 'plain',
            'content' => 'TOMBSTONE_REVISION_SECRET_CONTENT',
            'meta' => null,
            'moderation_status' => 'approved',
            'created_at' => now()->subMinutes(10),
        ]);
        PostModerationLog::query()->create([
            'post_id' => $deletedPost->id,
            'moderator_id' => $gm->id,
            'previous_status' => 'pending',
            'new_status' => 'approved',
            'reason' => 'TOMBSTONE_MODERATION_SECRET_NOTE',
            'created_at' => now()->subMinutes(9),
        ]);
        DiceRoll::query()->create([
            'scene_id' => $scene->id,
            'post_id' => $deletedPost->id,
            'user_id' => $author->id,
            'character_id' => null,
            'roll_mode' => 'normal',
            'modifier' => 2,
            'label' => 'TOMBSTONE_PROBE_SECRET_LABEL',
            'probe_attribute_key' => 'mut',
            'probe_target_value' => 42,
            'probe_is_success' => true,
            'rolls' => [4],
            'kept_roll' => 4,
            'total' => 6,
            'applied_le_delta' => -1,
            'applied_ae_delta' => 0,
            'resulting_le_current' => 29,
            'resulting_ae_current' => null,
            'is_critical_success' => false,
            'is_critical_failure' => false,
            'created_at' => now()->subMinutes(8),
        ]);
        PostReaction::query()->create([
            'post_id' => $deletedPost->id,
            'user_id' => $viewer->id,
            'emoji' => 'heart',
        ]);

        $deletedPost->forceFill(['deleted_by' => $deleter->id])->save();
        $deletedPost->delete();

        $response = $this->actingAs($viewer)->get(route('campaigns.scenes.show', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'scene' => $scene,
        ]));

        $response->assertOk();

        $html = (string) $response->getContent();
        $tombstoneHtml = $this->extractPostArticleHtml($html, (int) $deletedPost->id);

        $this->assertStringContainsString('Beitrag gelöscht.', $tombstoneHtml);
        $this->assertStringContainsString('Tombstone Author', $tombstoneHtml);
        $this->assertStringContainsString('data-reading-post-anchor', $tombstoneHtml);
        $this->assertStringContainsString('OLDER_LIVE_POST_CONTENT', $html);
        $this->assertStringContainsString('NEWER_LIVE_POST_CONTENT', $html);
        $this->assertPostIsBetween($html, $deletedPost, $oldestPost, $newestPost);

        foreach ([
            'TOMBSTONE_ORIGINAL_SECRET_CONTENT',
            'TOMBSTONE_IC_QUOTE_SECRET',
            'TOMBSTONE_REVISION_SECRET_CONTENT',
            'TOMBSTONE_MODERATION_SECRET_NOTE',
            'TOMBSTONE_PROBE_SECRET_LABEL',
            'TOMBSTONE_INVENTORY_CHARACTER',
            'TOMBSTONE_INVENTORY_ITEM',
            'Secret Deleter',
        ] as $forbiddenToken) {
            $this->assertStringNotContainsString($forbiddenToken, $html);
            $this->assertStringNotContainsString($forbiddenToken, $tombstoneHtml);
        }

        foreach ([
            '<form',
            '<input',
            '<button',
            'Bearbeiten',
            'Löschen',
            'Moderation',
            'Anpinnen',
            'Pin lösen',
            'Lesezeichen',
            'posts/'.$deletedPost->id.'/reactions',
            'posts/'.$deletedPost->id.'/edit',
            'posts/'.$deletedPost->id.'/moderate',
            'posts/'.$deletedPost->id.'/pin',
            'posts/'.$deletedPost->id.'/unpin',
            'data-post-type',
            'data-post-author-role',
            'deleted_by',
            'deleted_reason',
        ] as $forbiddenMarkup) {
            $this->assertStringNotContainsString($forbiddenMarkup, $tombstoneHtml);
        }
    }

    public function test_anchor_and_bookmark_urls_resolve_soft_deleted_posts_to_tombstones(): void
    {
        [$campaign, $scene, $gm] = $this->seedCampaignAndScene();
        $viewer = User::factory()->create();

        $posts = Post::factory()
            ->count(25)
            ->sequence(fn () => [
                'scene_id' => $scene->id,
                'user_id' => $gm->id,
                'post_type' => 'ooc',
                'content_format' => 'plain',
                'moderation_status' => 'approved',
            ])
            ->create();
        $deletedPost = $posts->get(4);
        $this->assertInstanceOf(Post::class, $deletedPost);
        $deletedPost->delete();

        SceneSubscription::query()->create([
            'scene_id' => $scene->id,
            'user_id' => $viewer->id,
            'is_muted' => false,
            'last_read_post_id' => $deletedPost->id,
            'last_read_at' => now()->subHour(),
        ]);
        SceneBookmark::query()->create([
            'user_id' => $viewer->id,
            'scene_id' => $scene->id,
            'post_id' => $deletedPost->id,
            'label' => 'Tombstone Marker',
        ]);

        $sceneResponse = $this->actingAs($viewer)->get(route('campaigns.scenes.show', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'scene' => $scene,
        ]));

        $sceneResponse->assertOk();
        $sceneResponse->assertSee('page=2#post-'.$deletedPost->id, false);

        $bookmarkResponse = $this->actingAs($viewer)->get(route('bookmarks.index', [
            'world' => $campaign->world,
        ]));

        $bookmarkResponse->assertOk();
        $bookmarkResponse->assertSee('page=2#post-'.$deletedPost->id, false);
    }

    public function test_read_state_jumps_and_mutating_routes_treat_tombstones_as_visible_but_not_mutable(): void
    {
        config(['features.wave4.reactions' => true]);

        [$campaign, $scene, $gm] = $this->seedCampaignAndScene();
        $viewer = User::factory()->create();

        $readPost = Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $gm->id,
            'post_type' => 'ooc',
            'content_format' => 'plain',
            'moderation_status' => 'approved',
        ]);
        $deletedPost = Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $gm->id,
            'post_type' => 'ooc',
            'content_format' => 'plain',
            'content' => 'TOMBSTONE_LATEST_UNREAD_SECRET',
            'moderation_status' => 'approved',
        ]);
        $deletedPost->delete();

        SceneSubscription::query()->create([
            'scene_id' => $scene->id,
            'user_id' => $viewer->id,
            'is_muted' => false,
            'last_read_post_id' => $readPost->id,
            'last_read_at' => now()->subHour(),
        ]);

        $threadResponse = $this->actingAs($viewer)->get(route('campaigns.scenes.thread', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'scene' => $scene,
        ]));

        $threadResponse->assertOk();
        $threadResponse->assertSee('Ungelesen: 1');
        $threadResponse->assertSee('post-'.$deletedPost->id, false);

        $latestJumpResponse = $this->actingAs($viewer)->get(route('campaigns.scenes.show', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'scene' => $scene,
            'jump' => 'latest',
        ]));

        $latestJumpResponse->assertRedirect(route('campaigns.scenes.show', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'scene' => $scene,
            'page' => 1,
        ]).'#post-'.$deletedPost->id);

        $this->actingAs($gm)
            ->get(route('posts.edit', ['world' => $campaign->world, 'post' => $deletedPost]))
            ->assertNotFound();
        $this->actingAs($gm)
            ->patch(route('posts.update', ['world' => $campaign->world, 'post' => $deletedPost]), [
                'post_type' => 'ooc',
                'content_format' => 'plain',
                'content' => 'Mutation must stay blocked.',
            ])
            ->assertNotFound();
        $this->actingAs($gm)
            ->delete(route('posts.destroy', ['world' => $campaign->world, 'post' => $deletedPost]))
            ->assertNotFound();
        $this->actingAs($gm)
            ->patch(route('posts.moderate', ['world' => $campaign->world, 'post' => $deletedPost]), [
                'moderation_status' => 'approved',
            ])
            ->assertNotFound();
        $this->actingAs($viewer)
            ->post(route('posts.reactions.store', ['world' => $campaign->world, 'post' => $deletedPost]), [
                'emoji' => 'heart',
            ])
            ->assertNotFound();
        $this->actingAs($viewer)
            ->post(route('campaigns.scenes.bookmark.store', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'scene' => $scene,
            ]), [
                'post_id' => $deletedPost->id,
            ])
            ->assertSessionHasErrors('post_id');
    }

    /**
     * @return array{0: Campaign, 1: Scene, 2: User}
     */
    private function seedCampaignAndScene(): array
    {
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

        return [$campaign, $scene, $gm];
    }

    private function extractPostArticleHtml(string $html, int $postId): string
    {
        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument;
        $document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $node = (new DOMXPath($document))
            ->query("//*[@id='post-".$postId."']")
            ?->item(0);

        $this->assertNotNull($node, 'Expected post article #post-'.$postId.' to exist.');

        return (string) $document->saveHTML($node);
    }

    private function assertPostIsBetween(string $html, Post $post, Post $firstNeighbor, Post $secondNeighbor): void
    {
        $postPosition = $this->postArticlePosition($html, $post);
        $firstNeighborPosition = $this->postArticlePosition($html, $firstNeighbor);
        $secondNeighborPosition = $this->postArticlePosition($html, $secondNeighbor);

        $this->assertGreaterThan(
            min($firstNeighborPosition, $secondNeighborPosition),
            $postPosition,
            'Expected post #'.$post->id.' to be rendered after one neighbor.'
        );
        $this->assertLessThan(
            max($firstNeighborPosition, $secondNeighborPosition),
            $postPosition,
            'Expected post #'.$post->id.' to be rendered before the other neighbor.'
        );
    }

    private function postArticlePosition(string $html, Post $post): int
    {
        $position = strpos($html, 'id="post-'.$post->id.'"');

        $this->assertNotFalse($position, 'Expected post #'.$post->id.' to be rendered.');

        return (int) $position;
    }
}
