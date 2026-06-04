<?php

namespace Tests\Unit;

use App\Models\Campaign;
use App\Models\Scene;
use App\Models\User;
use App\Support\SceneDescriptionRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SceneDescriptionRendererTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        config(['media-library.disk_name' => 'public']);
    }

    public function test_plain_scene_description_escapes_html_keeps_line_breaks_and_ignores_invalid_markers(): void
    {
        $renderer = app(SceneDescriptionRenderer::class);

        $result = $renderer->render("<b>Flamme</b>\nAsche\n\n[bild:5]", []);
        $html = $result->toHtml();

        $this->assertStringContainsString('&lt;b&gt;Flamme&lt;/b&gt;<br>', $html);
        $this->assertStringContainsString('Asche', $html);
        $this->assertStringContainsString('[bild:5]', $html);
        $this->assertSame([], $result->inlineMediaIds());
    }

    public function test_scene_description_renders_available_inline_image_and_tracks_media_id(): void
    {
        $renderer = app(SceneDescriptionRenderer::class);
        $scene = $this->createScene();
        $media = $scene
            ->addMedia(UploadedFile::fake()->image('scene-inline.jpg', 1200, 700))
            ->withCustomProperties(['slot' => 2])
            ->toMediaCollection(Scene::CONTENT_IMAGES_COLLECTION);

        $result = $renderer->render("Vorher\n\n[bild:2]\n\nNachher", $scene->getMedia(Scene::CONTENT_IMAGES_COLLECTION));
        $html = $result->toHtml();

        $this->assertStringContainsString('data-scene-inline-image-slot="2"', $html);
        $this->assertStringContainsString('src="'.$media->getUrl().'"', $html);
        $this->assertSame([(int) $media->id], $result->inlineMediaIds());
    }

    public function test_scene_description_renders_missing_slot_notice(): void
    {
        $renderer = app(SceneDescriptionRenderer::class);

        $result = $renderer->render('[bild:3]', []);

        $this->assertStringContainsString('data-scene-inline-image-missing="1"', $result->toHtml());
        $this->assertStringContainsString('Bild 3 nicht verfügbar', $result->toHtml());
        $this->assertSame([], $result->inlineMediaIds());
    }

    private function createScene(): Scene
    {
        $gm = User::factory()->gm()->create();
        $campaign = Campaign::factory()->create([
            'owner_id' => $gm->id,
            'status' => 'active',
            'is_public' => true,
        ]);

        return Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $gm->id,
            'status' => 'open',
            'mood' => 'neutral',
        ]);
    }
}
