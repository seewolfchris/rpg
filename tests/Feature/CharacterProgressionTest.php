<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignInvitation;
use App\Models\Character;
use App\Models\CharacterProgressionEvent;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CharacterProgressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_gm_can_award_milestone_xp_and_character_levels_up(): void
    {
        [$gm, $player, $campaign, $scene, $character] = $this->seedCampaignWithParticipantCharacter();

        $response = $this->actingAs($gm)->post(route('gm.progression.award-xp', ['world' => $campaign->world]), [
            'campaign_id' => $campaign->id,
            'scene_id' => $scene->id,
            'event_mode' => 'milestone',
            'reason' => 'Kapitel 1 abgeschlossen',
            'awards' => [[
                'character_id' => $character->id,
                'xp_delta' => 120,
            ]],
        ]);

        $response->assertRedirect(route('gm.progression.index', ['world' => $campaign->world, 'campaign_id' => $campaign->id]));

        $character->refresh();
        $this->assertSame(120, (int) $character->xp_total);
        $this->assertSame(2, (int) $character->level);
        $this->assertSame(8, (int) $character->attribute_points_unspent);

        $this->assertDatabaseHas('character_progression_events', [
            'character_id' => $character->id,
            'actor_user_id' => $gm->id,
            'campaign_id' => $campaign->id,
            'scene_id' => $scene->id,
            'event_type' => CharacterProgressionEvent::EVENT_XP_MILESTONE,
            'xp_delta' => 120,
            'level_before' => 1,
            'level_after' => 2,
        ]);
        $this->assertDatabaseHas('character_progression_events', [
            'character_id' => $character->id,
            'actor_user_id' => $gm->id,
            'campaign_id' => $campaign->id,
            'scene_id' => $scene->id,
            'event_type' => CharacterProgressionEvent::EVENT_LEVEL_UP_SYSTEM,
            'ap_delta' => 8,
            'level_before' => 1,
            'level_after' => 2,
        ]);
    }

    public function test_bulk_xp_award_rejects_invalid_target_without_partial_writes(): void
    {
        [$gm, $player, $campaign, $scene, $validCharacter] = $this->seedCampaignWithParticipantCharacter();
        $outsider = User::factory()->create();
        $invalidCharacter = Character::factory()->create([
            'user_id' => $outsider->id,
            'world_id' => $campaign->world_id,
        ]);

        $response = $this->actingAs($gm)
            ->from(route('gm.progression.index', ['world' => $campaign->world, 'campaign_id' => $campaign->id]))
            ->post(route('gm.progression.award-xp', ['world' => $campaign->world]), [
                'campaign_id' => $campaign->id,
                'scene_id' => $scene->id,
                'event_mode' => 'milestone',
                'awards' => [[
                    'character_id' => $validCharacter->id,
                    'xp_delta' => 40,
                ], [
                    'character_id' => $invalidCharacter->id,
                    'xp_delta' => 40,
                ]],
            ]);

        $response->assertRedirect(route('gm.progression.index', ['world' => $campaign->world, 'campaign_id' => $campaign->id]));
        $response->assertSessionHasErrors('awards.1.character_id');

        $validCharacter->refresh();
        $this->assertSame(0, (int) $validCharacter->xp_total);
        $this->assertDatabaseCount('character_progression_events', 0);
    }

    public function test_co_gm_can_award_xp_only_for_campaigns_where_they_are_co_gm(): void
    {
        $owner = User::factory()->gm()->create();
        $coGm = User::factory()->create();
        $player = User::factory()->create();

        $campaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'status' => 'active',
            'is_public' => true,
        ]);
        $otherCampaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'status' => 'active',
            'is_public' => true,
            'world_id' => $campaign->world_id,
        ]);

        $campaign->invitations()->create([
            'user_id' => $coGm->id,
            'invited_by' => $owner->id,
            'status' => CampaignInvitation::STATUS_ACCEPTED,
            'role' => CampaignInvitation::ROLE_CO_GM,
            'accepted_at' => now(),
            'responded_at' => now(),
            'created_at' => now(),
        ]);
        $campaign->invitations()->create([
            'user_id' => $player->id,
            'invited_by' => $owner->id,
            'status' => CampaignInvitation::STATUS_ACCEPTED,
            'role' => CampaignInvitation::ROLE_PLAYER,
            'accepted_at' => now(),
            'responded_at' => now(),
            'created_at' => now(),
        ]);

        $character = Character::factory()->create([
            'user_id' => $player->id,
            'world_id' => $campaign->world_id,
        ]);

        $this->actingAs($coGm)->post(route('gm.progression.award-xp', ['world' => $campaign->world]), [
            'campaign_id' => $campaign->id,
            'event_mode' => 'milestone',
            'awards' => [[
                'character_id' => $character->id,
                'xp_delta' => 30,
            ]],
        ])->assertRedirect(route('gm.progression.index', ['world' => $campaign->world, 'campaign_id' => $campaign->id]));

        $character->refresh();
        $this->assertSame(30, (int) $character->xp_total);

        $this->actingAs($coGm)
            ->from(route('gm.progression.index', ['world' => $campaign->world, 'campaign_id' => $campaign->id]))
            ->post(route('gm.progression.award-xp', ['world' => $campaign->world]), [
                'campaign_id' => $otherCampaign->id,
                'event_mode' => 'milestone',
                'awards' => [[
                    'character_id' => $character->id,
                    'xp_delta' => 30,
                ]],
            ])
            ->assertRedirect(route('gm.progression.index', ['world' => $campaign->world, 'campaign_id' => $campaign->id]))
            ->assertSessionHasErrors('campaign_id');
    }

    public function test_player_cannot_award_campaign_xp(): void
    {
        [$gm, $player, $campaign, $scene, $character] = $this->seedCampaignWithParticipantCharacter();

        $response = $this->actingAs($player)
            ->from(route('gm.progression.index', ['world' => $campaign->world, 'campaign_id' => $campaign->id]))
            ->post(route('gm.progression.award-xp', ['world' => $campaign->world]), [
                'campaign_id' => $campaign->id,
                'scene_id' => $scene->id,
                'event_mode' => 'milestone',
                'awards' => [[
                    'character_id' => $character->id,
                    'xp_delta' => 40,
                ]],
            ]);

        $response->assertRedirect(route('gm.progression.index', ['world' => $campaign->world, 'campaign_id' => $campaign->id]));
        $response->assertSessionHasErrors('campaign_id');
    }

    public function test_negative_correction_cannot_reduce_below_current_level_threshold(): void
    {
        [$gm, $player, $campaign, $scene, $character] = $this->seedCampaignWithParticipantCharacter();

        $character->update([
            'xp_total' => 120,
            'level' => 2,
            'attribute_points_unspent' => 8,
        ]);

        $response = $this->actingAs($gm)
            ->from(route('gm.progression.index', ['world' => $campaign->world, 'campaign_id' => $campaign->id]))
            ->post(route('gm.progression.award-xp', ['world' => $campaign->world]), [
                'campaign_id' => $campaign->id,
                'scene_id' => $scene->id,
                'event_mode' => 'correction',
                'reason' => 'Fehlbuchung',
                'awards' => [[
                    'character_id' => $character->id,
                    'xp_delta' => -30,
                ]],
            ]);

        $response->assertRedirect(route('gm.progression.index', ['world' => $campaign->world, 'campaign_id' => $campaign->id]));
        $response->assertSessionHasErrors('awards.0.xp_delta');

        $character->refresh();
        $this->assertSame(120, (int) $character->xp_total);
        $this->assertSame(2, (int) $character->level);
    }

    public function test_spending_attribute_points_updates_character_and_writes_event(): void
    {
        $owner = User::factory()->create();
        $character = Character::factory()->create([
            'user_id' => $owner->id,
            'species' => 'mensch',
            'calling' => 'heiler',
            'mu' => 40,
            'kl' => 45,
            'in' => 40,
            'ch' => 35,
            'ff' => 40,
            'ge' => 40,
            'ko' => 40,
            'kk' => 40,
            'strength' => 40,
            'dexterity' => 40,
            'constitution' => 40,
            'intelligence' => 45,
            'wisdom' => 40,
            'charisma' => 35,
            'level' => 3,
            'xp_total' => 355,
            'attribute_points_unspent' => 16,
            'le_max' => 40,
            'le_current' => 20,
            'ae_max' => 45,
            'ae_current' => 30,
        ]);

        $response = $this->actingAs($owner)->post(route('characters.progression.spend', $character), [
            'attribute_allocations' => [
                'mu' => 2,
                'ko' => 3,
                'kk' => 1,
            ],
            'note' => 'Belohnung nach Kapitelende',
        ]);

        $response->assertRedirect(route('characters.show', $character));

        $character->refresh();
        $this->assertSame(42, (int) $character->mu);
        $this->assertSame(43, (int) $character->ko);
        $this->assertSame(41, (int) $character->kk);
        $this->assertSame(10, (int) $character->attribute_points_unspent);
        $this->assertSame(42, (int) $character->le_max);
        $this->assertSame(20, (int) $character->le_current);
        $this->assertSame(45, (int) $character->ae_max);
        $this->assertSame(30, (int) $character->ae_current);

        $event = CharacterProgressionEvent::query()
            ->where('character_id', $character->id)
            ->where('event_type', CharacterProgressionEvent::EVENT_AP_SPEND)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(-6, (int) $event->ap_delta);
        $this->assertSame(['mu' => 2, 'ko' => 3, 'kk' => 1], $event->attribute_deltas);
    }

    public function test_spending_attribute_points_enforces_limits(): void
    {
        $owner = User::factory()->create();
        $character = Character::factory()->create([
            'user_id' => $owner->id,
            'mu' => 78,
            'level' => 2,
            'xp_total' => 100,
            'attribute_points_unspent' => 8,
        ]);

        $response = $this->actingAs($owner)
            ->from(route('characters.show', $character))
            ->post(route('characters.progression.spend', $character), [
                'attribute_allocations' => [
                    'mu' => 5,
                ],
            ]);

        $response->assertRedirect(route('characters.show', $character));
        $response->assertSessionHasErrors('attribute_allocations.mu');

        $character->refresh();
        $this->assertSame(78, (int) $character->mu);
        $this->assertSame(8, (int) $character->attribute_points_unspent);
    }

    public function test_progression_dashboard_lists_only_campaign_participant_characters(): void
    {
        [$gm, $player, $campaign, $scene, $character] = $this->seedCampaignWithParticipantCharacter();
        $outsider = User::factory()->create();
        $outsiderCharacter = Character::factory()->create([
            'user_id' => $outsider->id,
            'world_id' => $campaign->world_id,
            'name' => 'Nicht Teilnehmend',
        ]);

        $response = $this->actingAs($gm)->get(route('gm.progression.index', [
            'world' => $campaign->world,
            'campaign_id' => $campaign->id,
        ]));

        $response->assertOk()
            ->assertSeeText($character->name)
            ->assertDontSeeText($outsiderCharacter->name);
    }

    /**
     * @return array{0: User, 1: User, 2: Campaign, 3: Scene, 4: Character}
     */
    private function seedCampaignWithParticipantCharacter(): array
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
            'status' => 'open',
            'allow_ooc' => true,
        ]);

        $campaign->invitations()->create([
            'user_id' => $player->id,
            'invited_by' => $gm->id,
            'status' => CampaignInvitation::STATUS_ACCEPTED,
            'role' => CampaignInvitation::ROLE_PLAYER,
            'accepted_at' => now(),
            'responded_at' => now(),
            'created_at' => now(),
        ]);

        $character = Character::factory()->create([
            'user_id' => $player->id,
            'world_id' => $campaign->world_id,
            'xp_total' => 0,
            'level' => 1,
            'attribute_points_unspent' => 0,
        ]);

        return [$gm, $player, $campaign, $scene, $character];
    }
}
