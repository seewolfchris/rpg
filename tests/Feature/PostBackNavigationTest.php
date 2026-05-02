<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Post;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostBackNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_post_edit_uses_scene_post_anchor_back_link(): void
    {
        $user = User::factory()->create();
        $campaign = Campaign::factory()->create([
            'owner_id' => $user->id,
            'is_public' => true,
            'status' => 'active',
        ]);
        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $user->id,
            'status' => 'open',
        ]);
        $post = Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $user->id,
            'post_type' => 'ooc',
            'content_format' => 'markdown',
            'content' => 'Back-link test post.',
            'moderation_status' => 'approved',
        ]);

        $returnTo = route('campaigns.scenes.show', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]).'#post-'.$post->id;
        $expectedBack = $this->pathFromUrl($returnTo);

        $response = $this->actingAs($user)->get(route('posts.edit', [
            'world' => $campaign->world,
            'post' => $post,
            'return_to' => $returnTo,
        ]));

        $response->assertOk();
        $response->assertSee('href="'.$expectedBack.'"', false);
    }

    private function pathFromUrl(string $url): string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        $query = (string) parse_url($url, PHP_URL_QUERY);
        $fragment = (string) parse_url($url, PHP_URL_FRAGMENT);

        if ($query !== '') {
            $path .= '?'.$query;
        }
        if ($fragment !== '') {
            $path .= '#'.$fragment;
        }

        return $path;
    }
}
