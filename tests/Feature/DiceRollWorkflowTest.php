<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Character;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DiceRollWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_player_cannot_create_dice_roll_in_open_scene(): void
    {
        [$gm, $player, $campaign, $scene, $character] = $this->seedSceneContext('open');

        $response = $this->actingAs($player)->post(route('campaigns.scenes.dice-rolls.store', [$campaign, $scene]), [
            'dice_character_id' => $character->id,
            'dice_roll_mode' => 'normal',
            'dice_modifier' => 3,
            'dice_label' => 'Athletik-Check',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('dice_rolls', 0);
    }

    public function test_gm_can_create_dice_roll_in_open_scene(): void
    {
        [$gm, $player, $campaign, $scene, $character] = $this->seedSceneContext('open');

        $response = $this->actingAs($gm)->post(route('campaigns.scenes.dice-rolls.store', [$campaign, $scene]), [
            'dice_character_id' => $character->id,
            'dice_roll_mode' => 'normal',
            'dice_modifier' => 3,
            'dice_label' => 'Athletik-Check',
        ]);

        $response->assertRedirect(route('campaigns.scenes.show', [$campaign, $scene]).'#new-post-form');

        $this->assertDatabaseHas('dice_rolls', [
            'scene_id' => $scene->id,
            'user_id' => $gm->id,
            'character_id' => $character->id,
            'roll_mode' => 'normal',
            'modifier' => 3,
            'label' => 'Athletik-Check',
        ]);

        $roll = DB::table('dice_rolls')
            ->where('scene_id', $scene->id)
            ->where('user_id', $gm->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($roll);

        $rollValues = json_decode((string) $roll->rolls, true, 512, JSON_THROW_ON_ERROR);
        $this->assertCount(1, $rollValues);
        $this->assertGreaterThanOrEqual(1, $rollValues[0]);
        $this->assertLessThanOrEqual(100, $rollValues[0]);
        $this->assertSame((int) $roll->kept_roll + 3, (int) $roll->total);
    }

    public function test_gm_advantage_roll_keeps_the_highest_result(): void
    {
        [$gm, $player, $campaign, $scene, $character] = $this->seedSceneContext('open');

        $response = $this->actingAs($gm)->post(route('campaigns.scenes.dice-rolls.store', [$campaign, $scene]), [
            'dice_character_id' => $character->id,
            'dice_roll_mode' => 'advantage',
            'dice_modifier' => -1,
            'dice_label' => 'Wagemut',
        ]);

        $response->assertRedirect(route('campaigns.scenes.show', [$campaign, $scene]).'#new-post-form');

        $roll = DB::table('dice_rolls')
            ->where('scene_id', $scene->id)
            ->where('user_id', $gm->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($roll);

        $rollValues = json_decode((string) $roll->rolls, true, 512, JSON_THROW_ON_ERROR);
        $this->assertCount(2, $rollValues);
        $this->assertSame(max($rollValues), (int) $roll->kept_roll);
        $this->assertSame((int) $roll->kept_roll - 1, (int) $roll->total);
    }

    public function test_gm_can_roll_in_closed_scene(): void
    {
        [$gm, $player, $campaign, $scene, $character] = $this->seedSceneContext('closed');

        $response = $this->actingAs($gm)->post(route('campaigns.scenes.dice-rolls.store', [$campaign, $scene]), [
            'dice_character_id' => $character->id,
            'dice_roll_mode' => 'disadvantage',
            'dice_modifier' => 2,
            'dice_label' => 'Unsichtbarer Schritt',
        ]);

        $response->assertRedirect(route('campaigns.scenes.show', [$campaign, $scene]).'#new-post-form');

        $this->assertDatabaseHas('dice_rolls', [
            'scene_id' => $scene->id,
            'user_id' => $gm->id,
            'roll_mode' => 'disadvantage',
            'modifier' => 2,
        ]);
    }

    /**
     * @return array{0: User, 1: User, 2: Campaign, 3: Scene, 4: Character}
     */
    private function seedSceneContext(string $sceneStatus): array
    {
        $gm = User::factory()->gm()->create();
        $player = User::factory()->create();

        $campaign = Campaign::factory()->create([
            'owner_id' => $gm->id,
            'status' => 'active',
            'is_public' => true,
        ]);

        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $gm->id,
            'status' => $sceneStatus,
            'allow_ooc' => true,
        ]);

        $character = Character::factory()->create([
            'user_id' => $player->id,
        ]);

        return [$gm, $player, $campaign, $scene, $character];
    }
}
