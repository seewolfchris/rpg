<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BreadcrumbTrailTest extends TestCase
{
    use RefreshDatabase;

    public function test_scene_page_renders_breadcrumb_trail_and_keeps_back_link_and_badges(): void
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

        $response = $this->actingAs($gm)->get(route('campaigns.scenes.show', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'scene' => $scene,
        ]));

        $response->assertOk()
            ->assertSee('id="nav-unread-notifications-badge"', false)
            ->assertSee('id="nav-bookmark-count-badge"', false);

        $html = $response->getContent();
        $this->assertIsString($html);

        $xpath = $this->toXPath($html);

        $breadcrumbNavs = $xpath->query("//nav[@aria-label='Breadcrumb']");
        $this->assertSame(1, $breadcrumbNavs->length);

        $breadcrumbItems = $xpath->query("//nav[@aria-label='Breadcrumb']//ol/li[not(@aria-hidden='true')]");
        $this->assertSame(4, $breadcrumbItems->length);

        $labels = [];
        foreach ($breadcrumbItems as $breadcrumbItem) {
            $labels[] = $this->normalizeText($breadcrumbItem->textContent ?? '');
        }

        $this->assertSame([
            'Plattform',
            $campaign->world->name,
            $campaign->title,
            $scene->title,
        ], $labels);

        $currentNodes = $xpath->query("//nav[@aria-label='Breadcrumb']//li/*[@aria-current='page']");
        $this->assertSame(1, $currentNodes->length);
        $this->assertSame($scene->title, $this->normalizeText($currentNodes->item(0)?->textContent ?? ''));

        $this->assertStringContainsString('Weltbezogen', $html);
        $this->assertStringContainsString('Aktive Welt:', $html);
        $this->assertStringContainsString('Kampagne:', $html);
        $this->assertStringContainsString('Szene:', $html);

        $expectedBackHref = (string) parse_url(route('campaigns.show', [
            'world' => $campaign->world,
            'campaign' => $campaign,
        ]), PHP_URL_PATH);
        $this->assertNotSame('', $expectedBackHref);

        $backLinkNodes = $xpath->query("//a[@href='".$expectedBackHref."' and normalize-space()='Zurück']");
        $this->assertSame(1, $backLinkNodes->length);
    }

    private function toXPath(string $html): \DOMXPath
    {
        $document = new \DOMDocument();
        @$document->loadHTML($html);

        return new \DOMXPath($document);
    }

    private function normalizeText(string $input): string
    {
        $normalized = preg_replace('/\s+/u', ' ', trim($input));

        return $normalized === null ? trim($input) : $normalized;
    }
}
