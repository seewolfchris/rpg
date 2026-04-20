<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Campaign;

use App\Actions\Campaign\CreateCampaignAction;
use App\Actions\Campaign\DeleteCampaignAction;
use App\Actions\Campaign\UpdateCampaignAction;
use App\Models\Campaign;
use App\Models\User;
use App\Models\World;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignCrudActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_campaign_assigns_world_and_owner_in_action(): void
    {
        $world = World::factory()->create();
        $owner = User::factory()->gm()->create();

        $campaign = app(CreateCampaignAction::class)->execute($world, $owner, [
            'title' => 'Atomarer Feldzug',
            'slug' => 'atomarer-feldzug',
            'summary' => 'Kurz',
            'lore' => 'Lang',
            'status' => 'active',
            'is_public' => true,
            'requires_post_moderation' => false,
            'starts_at' => null,
            'ends_at' => null,
        ]);

        $this->assertDatabaseHas('campaigns', [
            'id' => $campaign->id,
            'world_id' => $world->id,
            'owner_id' => $owner->id,
            'slug' => 'atomarer-feldzug',
        ]);
    }

    public function test_update_campaign_throws_when_world_context_does_not_match(): void
    {
        $owner = User::factory()->gm()->create();
        $world = World::factory()->create();
        $otherWorld = World::factory()->create();
        $campaign = Campaign::factory()->create([
            'world_id' => $world->id,
            'owner_id' => $owner->id,
        ]);

        $this->expectException(ModelNotFoundException::class);

        app(UpdateCampaignAction::class)->execute($otherWorld, $campaign, [
            'title' => 'Darf nicht gehen',
        ]);
    }

    public function test_delete_campaign_removes_campaign_when_context_matches(): void
    {
        $owner = User::factory()->gm()->create();
        $world = World::factory()->create();
        $campaign = Campaign::factory()->create([
            'world_id' => $world->id,
            'owner_id' => $owner->id,
        ]);

        app(DeleteCampaignAction::class)->execute($world, $campaign);

        $this->assertDatabaseMissing('campaigns', [
            'id' => $campaign->id,
        ]);
    }
}
