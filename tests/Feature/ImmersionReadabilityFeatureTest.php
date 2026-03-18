<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Character;
use App\Models\Post;
use App\Models\Scene;
use App\Models\User;
use App\Models\World;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImmersionReadabilityFeatureTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function scenePayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Durch den Ascheregen',
            'slug' => 'durch-den-ascheregen',
            'summary' => 'Die Gruppe erreicht die zerstoerte Bastion.',
            'description' => 'Nebel und Asche liegen schwer in der Luft.',
            'status' => 'open',
            'mood' => 'mystic',
            'position' => 1,
            'allow_ooc' => '1',
        ], $overrides);
    }

    public function test_gm_can_create_scene_with_mood_header_image_and_previous_scene(): void
    {
        Storage::fake('public');

        $gm = User::factory()->gm()->create();
        $campaign = Campaign::factory()->create([
            'owner_id' => $gm->id,
            'status' => 'active',
        ]);
        $previousScene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $gm->id,
            'slug' => 'vorherige-szene',
            'position' => 1,
        ]);

        $response = $this->actingAs($gm)->post(
            route('campaigns.scenes.store', ['world' => $campaign->world, 'campaign' => $campaign]),
            $this->scenePayload([
                'slug' => 'ascheregen-ii',
                'mood' => 'dark',
                'position' => 2,
                'previous_scene_id' => $previousScene->id,
                'header_image' => UploadedFile::fake()->image('header.webp', 1400, 500),
            ]),
        );

        $response->assertRedirect();

        $scene = Scene::query()
            ->where('campaign_id', $campaign->id)
            ->where('slug', 'ascheregen-ii')
            ->firstOrFail();

        $this->assertSame('dark', $scene->mood);
        $this->assertSame($previousScene->id, $scene->previous_scene_id);
        $this->assertNotNull($scene->header_image_path);
        Storage::disk('public')->assertExists((string) $scene->header_image_path);
    }

    public function test_scene_store_rejects_previous_scene_outside_campaign_scope(): void
    {
        $gm = User::factory()->gm()->create();

        $campaign = Campaign::factory()->create([
            'owner_id' => $gm->id,
            'status' => 'active',
        ]);
        $foreignCampaign = Campaign::factory()->create([
            'owner_id' => $gm->id,
            'status' => 'active',
        ]);
        $foreignScene = Scene::factory()->create([
            'campaign_id' => $foreignCampaign->id,
            'created_by' => $gm->id,
        ]);

        $response = $this->actingAs($gm)
            ->from(route('campaigns.scenes.create', ['world' => $campaign->world, 'campaign' => $campaign]))
            ->post(
                route('campaigns.scenes.store', ['world' => $campaign->world, 'campaign' => $campaign]),
                $this->scenePayload([
                    'slug' => 'ungueltige-vorgaenger-szene',
                    'previous_scene_id' => $foreignScene->id,
                ]),
            );

        $response->assertRedirect(route('campaigns.scenes.create', ['world' => $campaign->world, 'campaign' => $campaign]));
        $response->assertSessionHasErrors('previous_scene_id');
    }

    public function test_scene_update_replaces_and_removes_header_image(): void
    {
        Storage::fake('public');

        $gm = User::factory()->gm()->create();
        $campaign = Campaign::factory()->create([
            'owner_id' => $gm->id,
            'status' => 'active',
        ]);
        $previousScene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $gm->id,
            'position' => 1,
        ]);

        Storage::disk('public')->put('scene-headers/original.webp', 'old-image');

        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $gm->id,
            'title' => 'Aschetor',
            'slug' => 'aschetor',
            'header_image_path' => 'scene-headers/original.webp',
            'position' => 2,
            'mood' => 'neutral',
        ]);

        $this->actingAs($gm)->patch(
            route('campaigns.scenes.update', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]),
            $this->scenePayload([
                'title' => 'Aschetor II',
                'slug' => 'aschetor-ii',
                'mood' => 'tense',
                'position' => 3,
                'previous_scene_id' => $previousScene->id,
                'header_image' => UploadedFile::fake()->image('replacement.png', 1200, 420),
            ]),
        )->assertRedirect();

        $scene->refresh();

        $this->assertSame('tense', $scene->mood);
        $this->assertSame($previousScene->id, $scene->previous_scene_id);
        $this->assertNotNull($scene->header_image_path);
        $this->assertNotSame('scene-headers/original.webp', $scene->header_image_path);
        Storage::disk('public')->assertMissing('scene-headers/original.webp');
        Storage::disk('public')->assertExists((string) $scene->header_image_path);

        $newHeaderPath = (string) $scene->header_image_path;

        $this->actingAs($gm)->patch(
            route('campaigns.scenes.update', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]),
            $this->scenePayload([
                'title' => 'Aschetor III',
                'slug' => 'aschetor-iii',
                'mood' => 'neutral',
                'position' => 4,
                'previous_scene_id' => null,
                'remove_header_image' => '1',
            ]),
        )->assertRedirect();

        $scene->refresh();

        $this->assertNull($scene->header_image_path);
        Storage::disk('public')->assertMissing($newHeaderPath);
    }

    public function test_scene_show_renders_previous_scene_link_and_ic_first_markup(): void
    {
        config([
            'features.wave3.editor_preview' => false,
            'features.wave3.draft_autosave' => false,
        ]);

        $gm = User::factory()->gm()->create();
        $campaign = Campaign::factory()->create([
            'owner_id' => $gm->id,
            'status' => 'active',
        ]);
        $character = Character::factory()->create([
            'user_id' => $gm->id,
            'world_id' => $campaign->world_id,
        ]);

        $previousScene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $gm->id,
            'title' => 'Die alten Gleise',
            'slug' => 'die-alten-gleise',
            'position' => 1,
        ]);

        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $gm->id,
            'title' => 'Im Bleichen Hafen',
            'slug' => 'im-bleichen-hafen',
            'previous_scene_id' => $previousScene->id,
            'position' => 2,
            'allow_ooc' => true,
        ]);

        Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $gm->id,
            'character_id' => $character->id,
            'post_type' => 'ic',
            'content_format' => 'markdown',
            'content' => 'Die Schiffe aechzten im Wind.',
            'moderation_status' => 'approved',
            'approved_by' => $gm->id,
            'approved_at' => now(),
        ]);
        Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $gm->id,
            'post_type' => 'ooc',
            'content_format' => 'plain',
            'content' => 'OOC: Termin am Freitag?',
            'moderation_status' => 'approved',
            'approved_by' => $gm->id,
            'approved_at' => now(),
        ]);

        $response = $this->actingAs($gm)->get(
            route('campaigns.scenes.show', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene])
        );

        $response->assertOk()
            ->assertSeeText('Diese Szene folgt auf:')
            ->assertSeeText($previousScene->title)
            ->assertSee('data-scene-thread-reading-mode', false)
            ->assertSee('data-ooc-thread', false)
            ->assertDontSee('data-draft-key="scene-'.$scene->id.'-user-'.$gm->id.'-new"', false)
            ->assertDontSee('data-ooc-thread open', false);
    }

    public function test_post_ic_quote_is_persisted_and_rendered_while_ooc_quote_is_rejected(): void
    {
        $gm = User::factory()->gm()->create();
        $campaign = Campaign::factory()->create([
            'owner_id' => $gm->id,
            'status' => 'active',
        ]);
        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $gm->id,
            'allow_ooc' => true,
        ]);
        $character = Character::factory()->create([
            'user_id' => $gm->id,
            'world_id' => $campaign->world_id,
        ]);

        $this->actingAs($gm)->post(
            route('campaigns.scenes.posts.store', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]),
            [
                'post_type' => 'ic',
                'character_id' => $character->id,
                'content_format' => 'markdown',
                'content' => 'Er blickte in die glimmenden Hallen.',
                'ic_quote' => 'Heute schweigt nur der Feind.',
            ],
        )->assertRedirect();

        $post = Post::query()->latest('id')->firstOrFail();
        $this->assertSame('Heute schweigt nur der Feind.', data_get($post->meta, 'ic_quote'));

        $this->actingAs($gm)->get(
            route('campaigns.scenes.show', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene])
        )->assertOk()
            ->assertSeeText('Heute schweigt nur der Feind.');

        $invalidResponse = $this->actingAs($gm)
            ->from(route('campaigns.scenes.show', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]))
            ->post(
                route('campaigns.scenes.posts.store', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]),
                [
                    'post_type' => 'ooc',
                    'content_format' => 'plain',
                    'content' => 'Kurz OOC abgestimmt.',
                    'ic_quote' => 'Das darf nicht gespeichert werden.',
                ],
            );

        $invalidResponse->assertRedirect(route('campaigns.scenes.show', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]));
        $invalidResponse->assertSessionHasErrors('ic_quote');
    }

    public function test_markdown_preview_endpoint_uses_sanitized_renderer_output(): void
    {
        config(['features.wave3.editor_preview' => true]);

        $user = User::factory()->create();
        $world = World::resolveDefault();

        $response = $this->actingAs($user)->postJson(route('posts.preview', ['world' => $world]), [
            'content_format' => 'markdown',
            'content' => "**Feuer**\n\n[spoiler]Geheim <script>alert(1)</script>[/spoiler]",
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonStructure(['status', 'html']);

        $html = (string) $response->json('html');

        $this->assertStringContainsString('<strong>Feuer</strong>', $html);
        $this->assertStringContainsString('<details', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }

    public function test_preview_endpoint_rejects_non_markdown_formats(): void
    {
        config(['features.wave3.editor_preview' => true]);

        $user = User::factory()->create();
        $world = World::resolveDefault();

        $response = $this->actingAs($user)->postJson(route('posts.preview', ['world' => $world]), [
            'content_format' => 'plain',
            'content' => 'Kein Markdown',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('content_format');
    }

    public function test_preview_endpoint_is_disabled_via_feature_flag(): void
    {
        config(['features.wave3.editor_preview' => false]);

        $user = User::factory()->create();
        $world = World::resolveDefault();

        $this->actingAs($user)->postJson(route('posts.preview', ['world' => $world]), [
            'content_format' => 'markdown',
            'content' => 'Test',
        ])->assertNotFound();
    }
}
