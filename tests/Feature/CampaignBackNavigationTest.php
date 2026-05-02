<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignBackNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_campaign_show_uses_campaign_index_fallback_back_link(): void
    {
        $user = User::factory()->gm()->create();
        $campaign = Campaign::factory()->create([
            'owner_id' => $user->id,
            'is_public' => false,
            'status' => 'active',
        ]);

        $expected = '/w/'.$campaign->world->slug.'/campaigns';

        $response = $this->actingAs($user)->get(route('campaigns.show', [
            'world' => $campaign->world,
            'campaign' => $campaign,
        ]));

        $response->assertOk();
        $response->assertSee('href="'.$expected.'"', false);
    }

    public function test_campaign_edit_uses_explicit_return_to_for_back_link_and_hidden_field(): void
    {
        $user = User::factory()->gm()->create();
        $campaign = Campaign::factory()->create([
            'owner_id' => $user->id,
            'is_public' => false,
            'status' => 'active',
        ]);

        $returnTo = '/notifications?page=2';

        $response = $this->actingAs($user)->get(route('campaigns.edit', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'return_to' => $returnTo,
        ]));

        $response->assertOk();
        $response->assertSee('href="'.$returnTo.'"', false);
        $response->assertSee('name="return_to"', false);
        $response->assertSee('value="'.$returnTo.'"', false);
    }
}
