<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CampaignRoutesCompatibilityTest extends TestCase
{
    public function test_campaign_route_contract_remains_compatible_after_campaign_route_split(): void
    {
        $this->assertRoute('campaigns.handouts.index', ['GET', 'HEAD'], 'w/{world}/campaigns/{campaign}/handouts');
        $this->assertRoute('campaigns.handouts.store', ['POST'], 'w/{world}/campaigns/{campaign}/handouts');
        $this->assertRoute('campaigns.handouts.reveal', ['PATCH'], 'w/{world}/campaigns/{campaign}/handouts/{handout}/reveal');

        $this->assertRoute('campaigns.story-log.index', ['GET', 'HEAD'], 'w/{world}/campaigns/{campaign}/story-log');
        $this->assertRoute('campaigns.story-log.show', ['GET', 'HEAD'], 'w/{world}/campaigns/{campaign}/story-log/{storyLogEntry}');
        $this->assertRoute('campaigns.story-log.reveal', ['PATCH'], 'w/{world}/campaigns/{campaign}/story-log/{storyLogEntry}/reveal');

        $this->assertRoute('campaigns.player-notes.index', ['GET', 'HEAD'], 'w/{world}/campaigns/{campaign}/player-notes');
        $this->assertRoute('campaigns.player-notes.show', ['GET', 'HEAD'], 'w/{world}/campaigns/{campaign}/player-notes/{playerNote}');

        $this->assertRoute('campaigns.scenes.thread', ['GET', 'HEAD'], 'w/{world}/campaigns/{campaign}/scenes/{scene}/thread');
        $this->assertRoute('scene-subscriptions.bulk-update', ['PATCH'], 'w/{world}/scene-subscriptions/bulk');
        $this->assertRoute('campaigns.scenes.bookmark.store', ['POST'], 'w/{world}/campaigns/{campaign}/scenes/{scene}/bookmark');
    }

    /**
     * @param  list<string>  $expectedMethods
     */
    private function assertRoute(string $name, array $expectedMethods, string $expectedUri): void
    {
        $route = Route::getRoutes()->getByName($name);
        $this->assertInstanceOf(IlluminateRoute::class, $route, 'Route not found: '.$name);

        $actualMethods = array_values(array_filter(
            $route->methods(),
            static fn (string $method): bool => $method !== 'OPTIONS'
        ));

        sort($actualMethods);
        $sortedExpectedMethods = $expectedMethods;
        sort($sortedExpectedMethods);

        $this->assertSame($sortedExpectedMethods, $actualMethods, 'Unexpected methods for route: '.$name);
        $this->assertSame($expectedUri, $route->uri(), 'Unexpected URI for route: '.$name);
    }
}
