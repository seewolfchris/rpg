<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Combat;

use App\Domain\Campaign\CampaignParticipantResolver;
use App\Domain\Combat\CombatPhaseService;
use App\Domain\Combat\CombatService;
use App\Domain\Combat\Data\CombatActionInput;
use App\Domain\Combat\Data\CombatActor;
use App\Domain\Combat\Data\CombatTarget;
use App\Domain\Combat\Exceptions\CombatInvariantViolationException;
use App\Enums\CampaignMembershipRole;
use App\Models\Campaign;
use App\Models\CampaignMembership;
use App\Models\Character;
use App\Models\CombatPhase;
use App\Models\CombatPhaseAction;
use App\Models\DiceRoll;
use App\Models\Scene;
use App\Models\User;
use App\Models\World;
use App\Support\ProbeRoller;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CombatPhaseServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_starts_first_phase_with_collecting_status(): void
    {
        [$owner, $campaign, $scene] = $this->campaignContext();

        $phase = $this->makeService([1])->startPhase($campaign, $scene, $owner);

        $this->assertSame(1, (int) $phase->phase_number);
        $this->assertSame(CombatPhase::STATUS_COLLECTING, (string) $phase->status);
        $this->assertSame((int) $campaign->id, (int) $phase->campaign_id);
        $this->assertSame((int) $scene->id, (int) $phase->scene_id);
        $this->assertSame((int) $owner->id, (int) $phase->started_by);
        $this->assertDatabaseCount('combat_phases', 1);
    }

    public function test_it_assigns_next_phase_number_after_previous_phase_was_resolved(): void
    {
        [$owner, $campaign, $scene] = $this->campaignContext();

        $service = $this->makeService([1]);
        $first = $service->startPhase($campaign, $scene, $owner);
        $first->status = CombatPhase::STATUS_RESOLVED;
        $first->resolved_by = (int) $owner->id;
        $first->resolved_at = now();
        $first->resolution_summary = ['summary' => 'done'];
        $first->save();

        $second = $service->startPhase($campaign, $scene, $owner);

        $this->assertSame(2, (int) $second->phase_number);
        $this->assertSame(2, CombatPhase::query()->count());
    }

    public function test_it_rejects_starting_a_second_open_phase_for_the_same_scene(): void
    {
        [$owner, $campaign, $scene] = $this->campaignContext();

        $service = $this->makeService([1]);
        $service->startPhase($campaign, $scene, $owner);

        $this->expectException(CombatInvariantViolationException::class);
        try {
            $service->startPhase($campaign, $scene, $owner);
        } catch (CombatInvariantViolationException $exception) {
            $this->assertSame('phase_already_collecting', $exception->reason());
            $this->assertSame('phase', $exception->field());

            throw $exception;
        }
    }

    public function test_it_queues_actions_in_position_order_without_mutating_character_le(): void
    {
        [$owner, $campaign, $scene] = $this->campaignContext();
        $participant = User::factory()->create();
        $this->grantPlayerMembership($campaign, $participant, $owner);

        $actor = $this->characterInCampaignWorld($owner, $campaign->world_id, ['name' => 'Arin']);
        $target = $this->characterInCampaignWorld($participant, $campaign->world_id, [
            'name' => 'Boros',
            'le_max' => 25,
            'le_current' => 25,
        ]);

        $service = $this->makeService([10]);
        $phase = $service->startPhase($campaign, $scene, $owner);

        $actionOne = $service->queueAction($phase, $this->makeInput($campaign, $scene, $actor, $target, [
            'damage' => 8,
            'armorProtection' => 1,
        ]));

        $actionTwo = $service->queueAction($phase, $this->makeInput($campaign, $scene, $actor, CombatTarget::npc('Wegraeuber'), [
            'damage' => 5,
            'armorProtection' => 0,
        ]));

        $target->refresh();
        $this->assertSame(25, (int) $target->le_current);
        $this->assertSame(1, (int) $actionOne->position);
        $this->assertSame(2, (int) $actionTwo->position);
        $this->assertDatabaseCount('combat_phase_actions', 2);
    }

    public function test_it_resolves_phase_actions_deterministically_and_persists_results(): void
    {
        [$owner, $campaign, $scene] = $this->campaignContext();
        $participant = User::factory()->create();
        $this->grantPlayerMembership($campaign, $participant, $owner);

        $actor = $this->characterInCampaignWorld($owner, $campaign->world_id, ['name' => 'Arel']);
        $target = $this->characterInCampaignWorld($participant, $campaign->world_id, [
            'name' => 'Dena',
            'le_max' => 20,
            'le_current' => 20,
        ]);

        $service = $this->makeService([20, 22]);
        $phase = $service->startPhase($campaign, $scene, $owner);

        $service->queueAction($phase, $this->makeInput($campaign, $scene, $actor, $target, [
            'damage' => 7,
            'armorProtection' => 2,
        ]));
        $service->queueAction($phase, $this->makeInput($campaign, $scene, $actor, CombatTarget::npc('Hafenraeuber I', [
            'le_current' => 12,
            'le_max' => 12,
            'armor_rs' => 1,
        ]), [
            'damage' => 4,
            'armorProtection' => null,
        ]));

        $result = $service->resolvePhase($phase, $owner);
        $phase->refresh();

        $this->assertSame(CombatPhase::STATUS_RESOLVED, (string) $phase->status);
        $this->assertSame((int) $owner->id, (int) $phase->resolved_by);
        $this->assertNotNull($phase->resolved_at);
        $this->assertIsArray($phase->resolution_summary);

        $actions = CombatPhaseAction::query()
            ->where('combat_phase_id', (int) $phase->id)
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $actions);
        $this->assertNotNull($actions[0]->result);
        $this->assertNotNull($actions[1]->result);
        $this->assertNotNull($actions[0]->resolved_at);
        $this->assertNotNull($actions[1]->resolved_at);

        $resultArray = $result->toArray();
        $this->assertSame((int) $phase->id, $resultArray['phase_id']);
        $this->assertSame(2, $resultArray['action_count']);
        $this->assertCount(2, $resultArray['results']);
        $this->assertSame(1, $resultArray['results'][0]['position']);
        $this->assertSame(2, $resultArray['results'][1]['position']);
    }

    public function test_it_applies_multiple_actions_to_same_character_target_in_order(): void
    {
        [$owner, $campaign, $scene] = $this->campaignContext();
        $participant = User::factory()->create();
        $this->grantPlayerMembership($campaign, $participant, $owner);

        $actor = $this->characterInCampaignWorld($owner, $campaign->world_id, ['name' => 'Kor']);
        $target = $this->characterInCampaignWorld($participant, $campaign->world_id, [
            'name' => 'Lyn',
            'le_max' => 20,
            'le_current' => 20,
            'armors' => [],
        ]);

        $service = $this->makeService([15, 16]);
        $phase = $service->startPhase($campaign, $scene, $owner);

        $service->queueAction($phase, $this->makeInput($campaign, $scene, $actor, $target, [
            'damage' => 7,
            'armorProtection' => 0,
        ]));
        $service->queueAction($phase, $this->makeInput($campaign, $scene, $actor, $target, [
            'damage' => 9,
            'armorProtection' => 0,
        ]));

        $service->resolvePhase($phase, $owner);

        $target->refresh();
        $this->assertSame(4, (int) $target->le_current);

        $actions = CombatPhaseAction::query()
            ->where('combat_phase_id', (int) $phase->id)
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        $this->assertSame(13, (int) data_get($actions[0]->result, 'outcome.resulting_le_current'));
        $this->assertSame(4, (int) data_get($actions[1]->result, 'outcome.resulting_le_current'));
    }

    public function test_it_rolls_back_phase_resolution_when_one_action_fails_invariant_checks(): void
    {
        [$owner, $campaign, $scene] = $this->campaignContext();
        $participant = User::factory()->create();
        $this->grantPlayerMembership($campaign, $participant, $owner);

        $actor = $this->characterInCampaignWorld($owner, $campaign->world_id);
        $target = $this->characterInCampaignWorld($participant, $campaign->world_id, [
            'le_max' => 20,
            'le_current' => 20,
            'armors' => [],
        ]);
        $outsider = User::factory()->create();
        $invalidTarget = $this->characterInCampaignWorld($outsider, $campaign->world_id, [
            'le_max' => 20,
            'le_current' => 20,
        ]);

        $service = $this->makeService([10, 10]);
        $phase = $service->startPhase($campaign, $scene, $owner);

        $service->queueAction($phase, $this->makeInput($campaign, $scene, $actor, $target, [
            'damage' => 6,
            'armorProtection' => 0,
        ]));

        CombatPhaseAction::query()->create([
            'combat_phase_id' => (int) $phase->id,
            'position' => 2,
            'actor_type' => CombatPhaseAction::TYPE_CHARACTER,
            'actor_character_id' => (int) $actor->id,
            'actor_name' => (string) $actor->name,
            'actor_snapshot' => ['character_id' => (int) $actor->id, 'name' => (string) $actor->name],
            'target_type' => CombatPhaseAction::TYPE_CHARACTER,
            'target_character_id' => (int) $invalidTarget->id,
            'target_name' => (string) $invalidTarget->name,
            'target_snapshot' => ['character_id' => (int) $invalidTarget->id, 'name' => (string) $invalidTarget->name],
            'weapon_name' => 'Kurzschwert',
            'attack_target_value' => 70,
            'attack_roll_mode' => DiceRoll::MODE_NORMAL,
            'attack_modifier' => 0,
            'defense_label' => null,
            'defense_target_value' => null,
            'defense_roll_mode' => null,
            'defense_modifier' => 0,
            'damage' => 5,
            'armor_protection' => 0,
            'intent_text' => null,
            'resolution_note' => null,
            'result' => null,
            'resolved_at' => null,
        ]);

        $this->expectException(CombatInvariantViolationException::class);
        try {
            $service->resolvePhase($phase, $owner);
        } catch (CombatInvariantViolationException $exception) {
            $this->assertSame('target_character_not_participant', $exception->reason());

            throw $exception;
        } finally {
            $phase->refresh();
            $target->refresh();

            $this->assertSame(CombatPhase::STATUS_COLLECTING, (string) $phase->status);
            $this->assertNull($phase->resolved_at);
            $this->assertNull($phase->resolution_summary);
            $this->assertSame(20, (int) $target->le_current);

            $storedActions = CombatPhaseAction::query()
                ->where('combat_phase_id', (int) $phase->id)
                ->orderBy('position')
                ->get();

            $this->assertCount(2, $storedActions);
            $this->assertNull($storedActions[0]->result);
            $this->assertNull($storedActions[0]->resolved_at);
            $this->assertNull($storedActions[1]->result);
            $this->assertNull($storedActions[1]->resolved_at);
        }
    }

    public function test_it_rejects_resolving_a_phase_without_actions(): void
    {
        [$owner, $campaign, $scene] = $this->campaignContext();

        $service = $this->makeService([1]);
        $phase = $service->startPhase($campaign, $scene, $owner);

        $this->expectException(CombatInvariantViolationException::class);
        try {
            $service->resolvePhase($phase, $owner);
        } catch (CombatInvariantViolationException $exception) {
            $this->assertSame('phase_has_no_actions', $exception->reason());
            $this->assertSame('phase', $exception->field());

            throw $exception;
        }
    }

    public function test_it_resolves_npc_target_without_persisting_character_state(): void
    {
        [$owner, $campaign, $scene] = $this->campaignContext();
        $participant = User::factory()->create();
        $this->grantPlayerMembership($campaign, $participant, $owner);

        $actor = $this->characterInCampaignWorld($owner, $campaign->world_id, ['name' => 'Ner']);
        $otherCharacter = $this->characterInCampaignWorld($participant, $campaign->world_id, [
            'name' => 'Unbeteiligt',
            'le_max' => 19,
            'le_current' => 19,
        ]);

        $service = $this->makeService([21]);
        $phase = $service->startPhase($campaign, $scene, $owner);
        $service->queueAction($phase, $this->makeInput(
            campaign: $campaign,
            scene: $scene,
            actor: $actor,
            target: CombatTarget::npc('Hafenraeuber II', [
                'le_current' => 10,
                'le_max' => 12,
                'armor_rs' => 2,
            ]),
            overrides: [
                'damage' => 7,
                'armorProtection' => null,
            ],
        ));

        $service->resolvePhase($phase, $owner);

        $otherCharacter->refresh();
        $this->assertSame(19, (int) $otherCharacter->le_current);

        $action = CombatPhaseAction::query()->where('combat_phase_id', (int) $phase->id)->firstOrFail();
        $this->assertSame('npc', (string) data_get($action->result, 'target.type'));
        $this->assertSame('Hafenraeuber II', (string) data_get($action->result, 'target.name'));
        $this->assertSame(5, (int) data_get($action->result, 'outcome.effective_damage'));
    }

    public function test_it_blocks_scene_campaign_mismatch_when_starting_a_phase(): void
    {
        [$owner, $campaign, $scene] = $this->campaignContext();
        $otherCampaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'world_id' => $campaign->world_id,
            'status' => 'active',
            'is_public' => true,
        ]);

        $this->expectException(CombatInvariantViolationException::class);
        try {
            $this->makeService([1])->startPhase($otherCampaign, $scene, $owner);
        } catch (CombatInvariantViolationException $exception) {
            $this->assertSame('scene_campaign_mismatch', $exception->reason());

            throw $exception;
        }
    }

    private function makeService(array $rolls): CombatPhaseService
    {
        $participantResolver = app(CampaignParticipantResolver::class);

        return new CombatPhaseService(
            combatService: new CombatService(
                probeRoller: new ProbeRoller($this->sequenceGenerator($rolls)),
                campaignParticipantResolver: $participantResolver,
            ),
            campaignParticipantResolver: $participantResolver,
        );
    }

    /**
     * @return array{0: User, 1: Campaign, 2: Scene}
     */
    private function campaignContext(): array
    {
        $owner = User::factory()->gm()->create();
        $campaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'status' => 'active',
            'is_public' => true,
        ]);
        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $owner->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);

        return [$owner, $campaign, $scene];
    }

    private function grantPlayerMembership(Campaign $campaign, User $participant, User $owner): void
    {
        CampaignMembership::query()->create([
            'campaign_id' => (int) $campaign->id,
            'user_id' => (int) $participant->id,
            'role' => CampaignMembershipRole::PLAYER->value,
            'assigned_by' => (int) $owner->id,
            'assigned_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function characterInCampaignWorld(User $user, int $worldId, array $overrides = []): Character
    {
        return Character::factory()->create(array_merge([
            'user_id' => $user->id,
            'world_id' => $worldId,
            'origin' => 'native_vhaltor',
            'species' => 'mensch',
            'calling' => 'abenteurer',
            'mu' => 40,
            'kl' => 40,
            'in' => 40,
            'ch' => 40,
            'ff' => 40,
            'ge' => 40,
            'ko' => 40,
            'kk' => 40,
            'strength' => 40,
            'dexterity' => 40,
            'constitution' => 40,
            'intelligence' => 40,
            'wisdom' => 40,
            'charisma' => 40,
            'le_max' => 20,
            'le_current' => 20,
            'ae_max' => 0,
            'ae_current' => 0,
            'armors' => [],
        ], $overrides));
    }

    /**
     * @param  Character|CombatTarget  $target
     * @param  array<string, mixed>  $overrides
     */
    private function makeInput(
        Campaign $campaign,
        Scene $scene,
        Character $actor,
        Character|CombatTarget $target,
        array $overrides = [],
    ): CombatActionInput {
        $targetDto = $target instanceof CombatTarget
            ? $target
            : CombatTarget::character($target);

        /** @var array<string, mixed> $payload */
        $payload = array_merge([
            'campaign' => $campaign,
            'scene' => $scene,
            'actor' => CombatActor::character($actor),
            'target' => $targetDto,
            'weaponName' => 'Testwaffe',
            'attackTargetValue' => 70,
            'attackRollMode' => DiceRoll::MODE_NORMAL,
            'attackModifier' => 0,
            'defenseLabel' => null,
            'defenseTargetValue' => null,
            'defenseRollMode' => DiceRoll::MODE_NORMAL,
            'defenseModifier' => 0,
            'damage' => 5,
            'armorProtection' => 0,
            'intentText' => null,
            'resolutionNote' => null,
        ], $overrides);

        return new CombatActionInput(
            campaign: $payload['campaign'],
            scene: $payload['scene'],
            actor: $payload['actor'],
            target: $payload['target'],
            weaponName: $payload['weaponName'],
            attackTargetValue: (int) $payload['attackTargetValue'],
            attackRollMode: (string) $payload['attackRollMode'],
            attackModifier: (int) $payload['attackModifier'],
            defenseLabel: is_string($payload['defenseLabel']) ? $payload['defenseLabel'] : null,
            defenseTargetValue: is_int($payload['defenseTargetValue']) ? $payload['defenseTargetValue'] : null,
            defenseRollMode: (string) $payload['defenseRollMode'],
            defenseModifier: (int) $payload['defenseModifier'],
            damage: (int) $payload['damage'],
            armorProtection: is_int($payload['armorProtection']) ? $payload['armorProtection'] : null,
            intentText: is_string($payload['intentText']) ? $payload['intentText'] : null,
            resolutionNote: is_string($payload['resolutionNote']) ? $payload['resolutionNote'] : null,
        );
    }

    /**
     * @param  list<int>  $rolls
     * @return Closure(): int
     */
    private function sequenceGenerator(array $rolls): Closure
    {
        return static function () use (&$rolls): int {
            $next = array_shift($rolls);

            return is_int($next) ? $next : 1;
        };
    }
}
