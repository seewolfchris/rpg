<?php

namespace Tests\Feature;

use App\Enums\CampaignMembershipRole;
use App\Models\Campaign;
use App\Models\CampaignMembership;
use App\Models\Handout;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HandoutBackNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_handout_show_from_scene_uses_scene_back_link(): void
    {
        [$user, $campaign, $scene] = $this->seedCampaignSceneContext();
        $handout = Handout::factory()->revealed()->forScene($scene)->create([
            'campaign_id' => $campaign->id,
            'created_by' => $user->id,
        ]);

        $sceneUrl = route('campaigns.scenes.show', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]);
        $expectedBack = $this->pathFromUrl($sceneUrl);

        $response = $this->actingAs($user)->get(route('campaigns.handouts.show', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'handout' => $handout,
            'return_to' => $sceneUrl,
        ]));

        $response->assertOk();
        $response->assertSee('href="'.$expectedBack.'"', false);
    }

    public function test_handout_show_rejects_invalid_external_return_to(): void
    {
        [$user, $campaign, $scene] = $this->seedCampaignSceneContext();
        $handout = Handout::factory()->forScene($scene)->create([
            'campaign_id' => $campaign->id,
            'created_by' => $user->id,
        ]);

        $fallbackBack = $this->pathFromUrl(route('campaigns.scenes.show', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'scene' => $scene,
        ]));

        $response = $this->actingAs($user)->get(route('campaigns.handouts.show', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'handout' => $handout,
            'return_to' => 'https://evil.example/phishing',
        ]));

        $response->assertOk();
        $response->assertSee('href="'.$fallbackBack.'"', false);
        $response->assertDontSee('https://evil.example/phishing');
    }

    /**
     * @return array{0: User, 1: Campaign, 2: Scene}
     */
    private function seedCampaignSceneContext(): array
    {
        $user = User::factory()->gm()->create();
        $campaign = Campaign::factory()->create([
            'owner_id' => $user->id,
            'is_public' => false,
            'status' => 'active',
        ]);
        CampaignMembership::factory()->gm()->create([
            'campaign_id' => $campaign->id,
            'user_id' => $user->id,
            'assigned_by' => $user->id,
            'role' => CampaignMembershipRole::GM->value,
        ]);
        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $user->id,
            'status' => 'open',
        ]);

        return [$user, $campaign, $scene];
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
