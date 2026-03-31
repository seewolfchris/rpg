<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignInvitation;
use App\Models\Character;
use App\Models\Post;
use App\Models\Scene;
use App\Models\User;
use App\Models\World;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationWorldContextMutationMatrixTest extends TestCase
{
    use RefreshDatabase;

    public function test_scene_store_role_matrix_for_active_world(): void
    {
        [$campaign, $owner, $coGm, $player, $outsider, $admin] = $this->seedCampaignRoleMatrix(worldActive: true);

        $cases = [
            'owner' => [$owner, 302],
            'co-gm' => [$coGm, 302],
            'admin' => [$admin, 302],
            'player' => [$player, 403],
            'outsider' => [$outsider, 403],
        ];

        foreach ($cases as $suffix => [$actor, $expectedStatus]) {
            $slug = 'a3-scene-'.$suffix;

            $response = $this->actingAs($actor)->post(route('campaigns.scenes.store', [
                'world' => $campaign->world,
                'campaign' => $campaign,
            ]), $this->scenePayload($slug, 'A3 '.$suffix));

            if ($expectedStatus === 302) {
                $response->assertRedirect();
                $this->assertDatabaseHas('scenes', [
                    'campaign_id' => $campaign->id,
                    'slug' => $slug,
                    'created_by' => $actor->id,
                ]);

                continue;
            }

            $response->assertStatus($expectedStatus);
            $this->assertDatabaseMissing('scenes', [
                'campaign_id' => $campaign->id,
                'slug' => $slug,
            ]);
        }
    }

    public function test_scene_store_in_inactive_world_returns_404_for_owner_co_gm_and_admin(): void
    {
        [$campaign, $owner, $coGm, , , $admin] = $this->seedCampaignRoleMatrix(worldActive: false);

        $actors = [
            'owner' => $owner,
            'co-gm' => $coGm,
            'admin' => $admin,
        ];

        foreach ($actors as $suffix => $actor) {
            $slug = 'a3-inactive-'.$suffix;

            $this->actingAs($actor)->post(route('campaigns.scenes.store', [
                'world' => $campaign->world,
                'campaign' => $campaign,
            ]), $this->scenePayload($slug, 'A3 inactive '.$suffix))
                ->assertNotFound();

            $this->assertDatabaseMissing('scenes', [
                'campaign_id' => $campaign->id,
                'slug' => $slug,
            ]);
        }
    }

    public function test_post_moderation_role_matrix_and_world_context_guards(): void
    {
        [$campaign, $owner, $coGm, $player, $outsider, $admin] = $this->seedCampaignRoleMatrix(worldActive: true);

        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $owner->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);

        $cases = [
            'owner' => [$owner, 302],
            'co-gm' => [$coGm, 302],
            'admin' => [$admin, 302],
            'player' => [$player, 403],
            'outsider' => [$outsider, 403],
        ];

        foreach ($cases as $suffix => [$actor, $expectedStatus]) {
            $post = Post::factory()->create([
                'scene_id' => $scene->id,
                'user_id' => $player->id,
                'moderation_status' => 'pending',
                'approved_at' => null,
                'approved_by' => null,
            ]);

            $response = $this->actingAs($actor)->patch(route('posts.moderate', [
                'world' => $campaign->world,
                'post' => $post,
            ]), [
                'moderation_status' => 'approved',
                'moderation_note' => 'A3 matrix '.$suffix,
            ]);

            if ($expectedStatus === 302) {
                $response->assertRedirect();
                $this->assertDatabaseHas('posts', [
                    'id' => $post->id,
                    'moderation_status' => 'approved',
                    'approved_by' => $actor->id,
                ]);

                continue;
            }

            $response->assertStatus($expectedStatus);
            $this->assertDatabaseHas('posts', [
                'id' => $post->id,
                'moderation_status' => 'pending',
                'approved_by' => null,
            ]);
        }

        $foreignActiveWorld = World::factory()->create([
            'slug' => 'a3-fremd-aktive-welt',
            'is_active' => true,
            'position' => -200,
        ]);
        $inactiveWorld = World::factory()->create([
            'slug' => 'a3-inaktive-welt',
            'is_active' => false,
            'position' => -210,
        ]);

        $worldGuardPost = Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $player->id,
            'moderation_status' => 'pending',
            'approved_at' => null,
            'approved_by' => null,
        ]);

        $this->actingAs($owner)->patch(route('posts.moderate', [
            'world' => $foreignActiveWorld,
            'post' => $worldGuardPost,
        ]), [
            'moderation_status' => 'approved',
            'moderation_note' => 'A3 wrong world',
        ])->assertNotFound();

        $this->assertDatabaseHas('posts', [
            'id' => $worldGuardPost->id,
            'moderation_status' => 'pending',
            'approved_by' => null,
        ]);

        $this->actingAs($owner)->patch(route('posts.moderate', [
            'world' => $inactiveWorld,
            'post' => $worldGuardPost,
        ]), [
            'moderation_status' => 'approved',
            'moderation_note' => 'A3 inactive world',
        ])->assertNotFound();

        $this->assertDatabaseHas('posts', [
            'id' => $worldGuardPost->id,
            'moderation_status' => 'pending',
            'approved_by' => null,
        ]);
    }

    public function test_character_inline_update_ownership_matrix(): void
    {
        $owner = User::factory()->create();
        $gm = User::factory()->gm()->create();
        $admin = User::factory()->admin()->create();
        $outsider = User::factory()->create();

        $cases = [
            'owner' => [$owner, 302],
            'gm' => [$gm, 302],
            'admin' => [$admin, 302],
            'outsider' => [$outsider, 403],
        ];

        foreach ($cases as $suffix => [$actor, $expectedStatus]) {
            $character = Character::factory()->create([
                'user_id' => $owner->id,
                'status' => 'active',
                'bio' => 'Ausgangszustand',
                'concept' => 'Baseline',
            ]);

            $response = $this->actingAs($actor)->patch(route('characters.inline-update', ['character' => $character]), [
                'status' => 'active',
                'bio' => 'A3 Matrix Bio '.$suffix,
                'concept' => 'A3 Matrix '.$suffix,
            ]);

            if ($expectedStatus === 302) {
                $response->assertRedirect(route('characters.show', ['character' => $character]));
                $this->assertDatabaseHas('characters', [
                    'id' => $character->id,
                    'concept' => 'A3 Matrix '.$suffix,
                ]);

                continue;
            }

            $response->assertStatus($expectedStatus);
            $this->assertDatabaseHas('characters', [
                'id' => $character->id,
                'concept' => 'Baseline',
            ]);
        }
    }

    public function test_gm_progression_award_xp_role_matrix_for_active_world(): void
    {
        [$campaign, $owner, $coGm, $player, $outsider, $admin] = $this->seedCampaignRoleMatrix(worldActive: true);

        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $owner->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);

        $cases = [
            'owner' => [$owner, 302],
            'co-gm' => [$coGm, 302],
            'admin' => [$admin, 302],
            'player' => [$player, 403],
            'outsider' => [$outsider, 403],
        ];

        foreach ($cases as $suffix => [$actor, $expectedStatus]) {
            $character = Character::factory()->create([
                'user_id' => $player->id,
                'world_id' => $campaign->world_id,
                'xp_total' => 0,
                'level' => 1,
            ]);

            $response = $this->actingAs($actor)->post(route('gm.progression.award-xp', [
                'world' => $campaign->world,
            ]), $this->gmProgressionPayload(
                campaignId: (int) $campaign->id,
                sceneId: (int) $scene->id,
                characterId: (int) $character->id,
                reason: 'A3 progression role '.$suffix,
            ));

            if ($expectedStatus === 302) {
                $response->assertRedirect(route('gm.progression.index', [
                    'world' => $campaign->world,
                    'campaign_id' => $campaign->id,
                ]));

                $this->assertDatabaseHas('characters', [
                    'id' => $character->id,
                    'xp_total' => 30,
                ]);
                $this->assertDatabaseHas('character_progression_events', [
                    'character_id' => $character->id,
                    'actor_user_id' => $actor->id,
                    'campaign_id' => $campaign->id,
                    'scene_id' => $scene->id,
                    'xp_delta' => 30,
                ]);

                continue;
            }

            $response->assertStatus($expectedStatus);
            $this->assertDatabaseHas('characters', [
                'id' => $character->id,
                'xp_total' => 0,
            ]);
            $this->assertDatabaseMissing('character_progression_events', [
                'character_id' => $character->id,
                'actor_user_id' => $actor->id,
                'campaign_id' => $campaign->id,
            ]);
        }
    }

    public function test_gm_progression_award_xp_ownership_and_world_context_guards(): void
    {
        [$campaign, $owner, $coGm, $player, $outsider, $admin] = $this->seedCampaignRoleMatrix(worldActive: true);
        unset($coGm, $admin);

        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $owner->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);

        $participantCharacter = Character::factory()->create([
            'user_id' => $player->id,
            'world_id' => $campaign->world_id,
            'xp_total' => 0,
            'level' => 1,
        ]);
        $foreignCharacter = Character::factory()->create([
            'user_id' => $outsider->id,
            'world_id' => $campaign->world_id,
            'xp_total' => 0,
            'level' => 1,
        ]);

        $this->actingAs($owner)->post(route('gm.progression.award-xp', [
            'world' => $campaign->world,
        ]), $this->gmProgressionPayload(
            campaignId: (int) $campaign->id,
            sceneId: (int) $scene->id,
            characterId: (int) $participantCharacter->id,
            reason: 'A3 progression ownership ok',
        ))->assertRedirect(route('gm.progression.index', [
            'world' => $campaign->world,
            'campaign_id' => $campaign->id,
        ]));

        $this->assertDatabaseHas('characters', [
            'id' => $participantCharacter->id,
            'xp_total' => 30,
        ]);

        $this->actingAs($owner)
            ->from(route('gm.progression.index', ['world' => $campaign->world, 'campaign_id' => $campaign->id]))
            ->post(route('gm.progression.award-xp', [
                'world' => $campaign->world,
            ]), $this->gmProgressionPayload(
                campaignId: (int) $campaign->id,
                sceneId: (int) $scene->id,
                characterId: (int) $foreignCharacter->id,
                reason: 'A3 progression ownership reject',
            ))
            ->assertRedirect(route('gm.progression.index', ['world' => $campaign->world, 'campaign_id' => $campaign->id]))
            ->assertSessionHasErrors('awards.0.character_id');

        $this->assertDatabaseHas('characters', [
            'id' => $foreignCharacter->id,
            'xp_total' => 0,
        ]);
        $this->assertDatabaseHas('characters', [
            'id' => $participantCharacter->id,
            'xp_total' => 30,
        ]);

        $foreignActiveWorld = World::factory()->create([
            'slug' => 'a3-progression-fremd-aktiv',
            'is_active' => true,
            'position' => -330,
        ]);
        $inactiveWorld = World::factory()->create([
            'slug' => 'a3-progression-inaktiv',
            'is_active' => false,
            'position' => -340,
        ]);

        $this->actingAs($owner)
            ->from(route('gm.progression.index', ['world' => $foreignActiveWorld]))
            ->post(route('gm.progression.award-xp', [
                'world' => $foreignActiveWorld,
            ]), $this->gmProgressionPayload(
                campaignId: (int) $campaign->id,
                sceneId: (int) $scene->id,
                characterId: (int) $participantCharacter->id,
                reason: 'A3 progression wrong world',
            ))
            ->assertRedirect(route('gm.progression.index', ['world' => $foreignActiveWorld]))
            ->assertSessionHasErrors('campaign_id');

        $this->assertDatabaseHas('characters', [
            'id' => $participantCharacter->id,
            'xp_total' => 30,
        ]);

        $this->actingAs($owner)->post(route('gm.progression.award-xp', [
            'world' => $inactiveWorld,
        ]), $this->gmProgressionPayload(
            campaignId: (int) $campaign->id,
            sceneId: (int) $scene->id,
            characterId: (int) $participantCharacter->id,
            reason: 'A3 progression inactive world',
        ))->assertNotFound();

        $this->assertDatabaseHas('characters', [
            'id' => $participantCharacter->id,
            'xp_total' => 30,
        ]);
    }

    public function test_scene_inventory_quick_action_role_matrix_for_active_world(): void
    {
        [$campaign, $owner, $coGm, $player, $outsider, $admin] = $this->seedCampaignRoleMatrix(worldActive: true);

        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $owner->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);

        $cases = [
            'owner' => [$owner, true],
            'co-gm' => [$coGm, true],
            'admin' => [$admin, true],
            'player' => [$player, false],
            'outsider' => [$outsider, false],
        ];

        foreach ($cases as $suffix => [$actor, $shouldMutate]) {
            $character = Character::factory()->create([
                'user_id' => $player->id,
                'world_id' => $campaign->world_id,
                'inventory' => ['Fackel'],
            ]);

            $response = $this->actingAs($actor)
                ->from(route('campaigns.scenes.show', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]))
                ->post(route('campaigns.scenes.inventory-quick-action', [
                    'world' => $campaign->world,
                    'campaign' => $campaign,
                    'scene' => $scene,
                ]), $this->inventoryQuickActionPayload((int) $character->id, [
                    'inventory_action_item' => 'Heiltrank '.$suffix,
                ]));

            $expectedRedirect = route('campaigns.scenes.show', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'scene' => $scene,
            ]);

            $response->assertRedirect(
                $shouldMutate ? $expectedRedirect.'#inventory-quick-action' : $expectedRedirect
            );

            $character->refresh();
            $inventory = is_array($character->inventory) ? $character->inventory : [];

            if ($shouldMutate) {
                $this->assertCount(2, $inventory);
                $this->assertSame('Heiltrank '.$suffix, (string) ($inventory[1]['name'] ?? ''));
                $this->assertSame(2, (int) ($inventory[1]['quantity'] ?? 0));
                $this->assertDatabaseHas('character_inventory_logs', [
                    'character_id' => $character->id,
                    'actor_user_id' => $actor->id,
                    'source' => 'scene_inventory_quick_action',
                    'action' => 'add',
                    'item_name' => 'Heiltrank '.$suffix,
                    'quantity' => 2,
                ]);

                continue;
            }

            $response->assertSessionHasErrors('inventory_action_character_id');
            $this->assertSame(['Fackel'], $inventory);
            $this->assertDatabaseMissing('character_inventory_logs', [
                'character_id' => $character->id,
                'actor_user_id' => $actor->id,
                'source' => 'scene_inventory_quick_action',
                'action' => 'add',
                'item_name' => 'Heiltrank '.$suffix,
            ]);
        }
    }

    public function test_scene_inventory_quick_action_ownership_and_world_context_guards(): void
    {
        [$campaign, $owner, , $player, $outsider] = $this->seedCampaignRoleMatrix(worldActive: true);

        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $owner->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);

        $participantCharacter = Character::factory()->create([
            'user_id' => $player->id,
            'world_id' => $campaign->world_id,
            'inventory' => ['Fackel'],
        ]);
        $nonParticipantCharacter = Character::factory()->create([
            'user_id' => $outsider->id,
            'world_id' => $campaign->world_id,
            'inventory' => ['Dolch'],
        ]);

        $this->actingAs($owner)
            ->from(route('campaigns.scenes.show', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]))
            ->post(route('campaigns.scenes.inventory-quick-action', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'scene' => $scene,
            ]), $this->inventoryQuickActionPayload((int) $participantCharacter->id, [
                'inventory_action_item' => 'Seil',
            ]))
            ->assertRedirect(route('campaigns.scenes.show', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'scene' => $scene,
            ]).'#inventory-quick-action');

        $this->actingAs($owner)
            ->from(route('campaigns.scenes.show', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]))
            ->post(route('campaigns.scenes.inventory-quick-action', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'scene' => $scene,
            ]), $this->inventoryQuickActionPayload((int) $nonParticipantCharacter->id, [
                'inventory_action_item' => 'Heiltrank',
            ]))
            ->assertRedirect(route('campaigns.scenes.show', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'scene' => $scene,
            ]))
            ->assertSessionHasErrors('inventory_action_character_id');

        $this->assertSame(['Dolch'], $nonParticipantCharacter->fresh()->inventory);

        $foreignActiveWorld = World::factory()->create([
            'slug' => 'a3-inventory-fremd-aktiv',
            'is_active' => true,
            'position' => -350,
        ]);

        $this->actingAs($owner)->post(route('campaigns.scenes.inventory-quick-action', [
            'world' => $foreignActiveWorld,
            'campaign' => $campaign,
            'scene' => $scene,
        ]), $this->inventoryQuickActionPayload((int) $participantCharacter->id, [
            'inventory_action_item' => 'Fremdwelt',
        ]))->assertNotFound();

        [$inactiveCampaign, $inactiveOwner, , $inactivePlayer] = $this->seedCampaignRoleMatrix(worldActive: false);
        $inactiveScene = Scene::factory()->create([
            'campaign_id' => $inactiveCampaign->id,
            'created_by' => $inactiveOwner->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);
        $inactiveParticipantCharacter = Character::factory()->create([
            'user_id' => $inactivePlayer->id,
            'world_id' => $inactiveCampaign->world_id,
            'inventory' => ['Fackel'],
        ]);

        $this->actingAs($inactiveOwner)->post(route('campaigns.scenes.inventory-quick-action', [
            'world' => $inactiveCampaign->world,
            'campaign' => $inactiveCampaign,
            'scene' => $inactiveScene,
        ]), $this->inventoryQuickActionPayload((int) $inactiveParticipantCharacter->id, [
            'inventory_action_item' => 'Inaktive Welt',
        ]))->assertNotFound();

        $this->assertSame(['Fackel'], $inactiveParticipantCharacter->fresh()->inventory);
    }

    /**
     * @return array{Campaign, User, User, User, User, User}
     */
    private function seedCampaignRoleMatrix(bool $worldActive): array
    {
        $owner = User::factory()->gm()->create();
        $coGm = User::factory()->create();
        $player = User::factory()->create();
        $outsider = User::factory()->create();
        $admin = User::factory()->admin()->create();

        $world = World::factory()->create([
            'slug' => $worldActive ? 'a3-aktive-welt' : 'a3-inaktive-kampagnenwelt',
            'is_active' => $worldActive,
            'position' => -300,
        ]);

        $campaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'world_id' => $world->id,
            'status' => 'active',
            'is_public' => false,
        ]);

        $this->acceptInvitation($campaign, $coGm, CampaignInvitation::ROLE_CO_GM, $owner);
        $this->acceptInvitation($campaign, $player, CampaignInvitation::ROLE_PLAYER, $owner);

        return [$campaign, $owner, $coGm, $player, $outsider, $admin];
    }

    private function acceptInvitation(Campaign $campaign, User $user, string $role, User $inviter): void
    {
        CampaignInvitation::query()->create([
            'campaign_id' => $campaign->id,
            'user_id' => $user->id,
            'invited_by' => $inviter->id,
            'status' => CampaignInvitation::STATUS_ACCEPTED,
            'role' => $role,
            'accepted_at' => now(),
            'responded_at' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function scenePayload(string $slug, string $title): array
    {
        return [
            'title' => $title,
            'slug' => $slug,
            'summary' => 'A3 Matrix Szenen-Create',
            'status' => 'open',
            'mood' => 'neutral',
            'position' => 1,
            'allow_ooc' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function gmProgressionPayload(
        int $campaignId,
        int $sceneId,
        int $characterId,
        string $reason
    ): array {
        return [
            'campaign_id' => $campaignId,
            'scene_id' => $sceneId,
            'event_mode' => 'milestone',
            'reason' => $reason,
            'awards' => [[
                'character_id' => $characterId,
                'xp_delta' => 30,
            ]],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function inventoryQuickActionPayload(int $characterId, array $overrides = []): array
    {
        return array_merge([
            'inventory_action_character_id' => $characterId,
            'inventory_action_type' => 'add',
            'inventory_action_item' => 'Heiltrank',
            'inventory_action_quantity' => 2,
            'inventory_action_equipped' => '0',
            'inventory_action_note' => 'A3 Matrix',
        ], $overrides);
    }
}
