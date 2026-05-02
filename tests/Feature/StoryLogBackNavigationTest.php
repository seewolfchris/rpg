<?php

namespace Tests\Feature;

use App\Enums\CampaignMembershipRole;
use App\Models\Campaign;
use App\Models\CampaignMembership;
use App\Models\Scene;
use App\Models\StoryLogEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoryLogBackNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_story_log_show_from_scene_uses_scene_back_link(): void
    {
        [$user, $campaign, $scene] = $this->seedCampaignSceneContext();
        $entry = StoryLogEntry::factory()->revealed()->forScene($scene)->create([
            'campaign_id' => $campaign->id,
            'created_by' => $user->id,
        ]);

        $sceneUrl = route('campaigns.scenes.show', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]);
        $expectedBack = $this->pathFromUrl($sceneUrl);

        $response = $this->actingAs($user)->get(route('campaigns.story-log.show', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'storyLogEntry' => $entry,
            'return_to' => $sceneUrl,
        ]));

        $response->assertOk();
        $response->assertSee('href="'.$expectedBack.'"', false);
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
