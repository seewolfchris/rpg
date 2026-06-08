<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\World;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class NavigationActiveStateTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('guestGlobalNavigationCases')]
    public function test_global_navigation_sets_aria_current_for_guest_routes(
        string $routeName,
        string $expectedLabel
    ): void {
        $response = $this->get(route($routeName));
        $response->assertOk();

        $html = $response->getContent();
        $this->assertIsString($html);

        $this->assertSingleCurrentInGlobalNavigation($html, $expectedLabel);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: bool}>
     */
    public static function authenticatedGlobalNavigationCases(): array
    {
        return [
            'dashboard' => ['dashboard', 'Dashboard', false],
            'leaderboard' => ['leaderboard.index', 'Rangliste', false],
            'campaigns' => ['campaigns.index', 'Kampagnen', true],
            'characters' => ['characters.index', 'Charaktere', false],
            'notifications' => ['notifications.index', 'Mitteilungen', false],
            'subscriptions' => ['scene-subscriptions.index', 'Abos', true],
            'bookmarks' => ['bookmarks.index', 'Lesezeichen', true],
            'invitations' => ['campaign-invitations.index', 'Einladungen', false],
            'gm' => ['gm.index', 'GM-Bereich', false],
            'admin' => ['admin.users.index', 'Benutzerverwaltung', false],
        ];
    }

    #[DataProvider('authenticatedGlobalNavigationCases')]
    public function test_global_navigation_sets_aria_current_for_authenticated_routes(
        string $routeName,
        string $expectedLabel,
        bool $requiresWorld
    ): void {
        $user = User::factory()->admin()->create();
        $world = World::factory()->create();

        $parameters = $requiresWorld ? ['world' => $world] : [];

        $response = $this->actingAs($user)->get(route($routeName, $parameters));
        $response->assertOk();

        $html = $response->getContent();
        $this->assertIsString($html);

        $this->assertSingleCurrentInGlobalNavigation($html, $expectedLabel);
    }

    public function test_knowledge_navigation_has_exactly_one_active_aria_current_entry(): void
    {
        config(['content.world_markdown_preview' => true]);

        $world = World::factory()->create();

        $rulesResponse = $this->get(route('knowledge.rules', ['world' => $world]));
        $rulesResponse->assertOk();

        $rulesHtml = $rulesResponse->getContent();
        $this->assertIsString($rulesHtml);
        $this->assertKnowledgeNavigationHasSingleCurrentEntry($rulesHtml);

        $overviewResponse = $this->get(route('knowledge.world-overview', ['world' => $world]));
        $overviewResponse->assertOk();

        $overviewHtml = $overviewResponse->getContent();
        $this->assertIsString($overviewHtml);
        $this->assertKnowledgeNavigationHasSingleCurrentEntry($overviewHtml);
    }

    public function test_global_navigation_badge_ids_remain_in_markup(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('notifications.index'));
        $response->assertOk()
            ->assertSee('id="nav-unread-notifications-badge"', false)
            ->assertSee('id="nav-bookmark-count-badge"', false);
    }

    public function test_authenticated_navigation_separates_primary_personal_and_management_groups(): void
    {
        $user = User::factory()->admin()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertOk()
            ->assertSeeText('Hauptnavigation')
            ->assertSeeText('Meine Bereiche')
            ->assertSeeText('Verwaltung');

        $html = $response->getContent();
        $this->assertIsString($html);

        $xpath = $this->toXPath($html);

        $primaryLinks = $xpath->query("//nav[@aria-label='Hauptnavigation']//div[@data-nav-group='primary']//a");
        $secondaryLinks = $xpath->query("//nav[@aria-label='Hauptnavigation']//div[@data-nav-group='secondary']//a");
        $managementLinks = $xpath->query("//nav[@aria-label='Hauptnavigation']//div[@data-nav-group='management']//a");

        $this->assertGreaterThanOrEqual(6, $primaryLinks->length);
        $this->assertSame(4, $secondaryLinks->length);
        $this->assertGreaterThanOrEqual(1, $managementLinks->length);
    }

    public function test_nested_campaign_pages_do_not_set_global_aria_current_on_parent_link(): void
    {
        $user = User::factory()->create();
        $world = World::factory()->create();
        $campaign = \App\Models\Campaign::factory()->create([
            'world_id' => $world->id,
            'owner_id' => $user->id,
            'is_public' => true,
        ]);

        $response = $this->actingAs($user)->get(route('campaigns.show', [
            'world' => $world,
            'campaign' => $campaign,
        ]));
        $response->assertOk();

        $html = $response->getContent();
        $this->assertIsString($html);

        $xpath = $this->toXPath($html);
        $currentNodes = $xpath->query("//nav[@aria-label='Hauptnavigation']//a[@aria-current='page']");

        $this->assertSame(0, $currentNodes->length);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function guestGlobalNavigationCases(): array
    {
        return [
            'worlds' => ['worlds.index', 'Welten'],
            'knowledge' => ['knowledge.global.index', 'Wissen'],
        ];
    }

    private function assertSingleCurrentInGlobalNavigation(string $html, string $expectedLabel): void
    {
        $xpath = $this->toXPath($html);
        $currentNodes = $xpath->query("//nav[@aria-label='Hauptnavigation']//a[@aria-current='page']");

        $this->assertSame(1, $currentNodes->length);

        $currentLabel = $this->normalizeText($currentNodes->item(0)?->textContent ?? '');
        $this->assertSame($expectedLabel, $currentLabel);
    }

    private function assertKnowledgeNavigationHasSingleCurrentEntry(string $html): void
    {
        $xpath = $this->toXPath($html);
        $currentNodes = $xpath->query("//nav[@aria-label='Wissenszentrum Navigation']//a[@aria-current='page']");

        $this->assertSame(1, $currentNodes->length);
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
