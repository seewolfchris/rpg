<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Combat;

use App\Domain\Campaign\CampaignParticipantResolver;
use App\Domain\Combat\CombatService;
use App\Domain\Combat\Data\CombatActionInput;
use App\Domain\Combat\Data\CombatActor;
use App\Domain\Combat\Data\CombatTarget;
use App\Domain\Combat\Exceptions\CombatInvariantViolationException;
use App\Enums\CampaignMembershipRole;
use App\Models\Campaign;
use App\Models\CampaignMembership;
use App\Models\Character;
use App\Models\DiceRoll;
use App\Models\Scene;
use App\Models\User;
use App\Models\World;
use App\Support\ProbeRoller;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CombatServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_resolves_single_character_attack_and_applies_le_damage_after_failed_defense(): void
    {
        [$owner, $campaign, $scene] = $this->campaignContext();
        $participant = User::factory()->create();
        $this->grantPlayerMembership($campaign, $participant, $owner);

        $actorCharacter = $this->characterInCampaignWorld($owner, $campaign->world_id, [
            'name' => 'Arvid',
        ]);
        $targetCharacter = $this->characterInCampaignWorld($participant, $campaign->world_id, [
            'name' => 'Brenna',
            'le_max' => 20,
            'le_current' => 20,
            'armors' => [[
                'name' => 'Kettenhemd',
                'protection' => 3,
                'equipped' => true,
            ]],
        ]);

        $service = $this->makeService([25, 95]);
        $result = $service->resolveSingleAction(new CombatActionInput(
            campaign: $campaign,
            scene: $scene,
            actor: CombatActor::character($actorCharacter),
            target: CombatTarget::character($targetCharacter),
            weaponName: 'Langschwert',
            attackTargetValue: 70,
            attackRollMode: DiceRoll::MODE_NORMAL,
            attackModifier: 0,
            defenseLabel: 'Parade',
            defenseTargetValue: 60,
            defenseRollMode: DiceRoll::MODE_NORMAL,
            defenseModifier: 0,
            damage: 12,
            armorProtection: null,
            intentText: 'Ich druecke den Gegner gegen die Wand.',
            resolutionNote: 'Knapper Schlag, aber sauber gesetzt.',
        ));

        $targetCharacter->refresh();
        $this->assertSame(11, (int) $targetCharacter->le_current);

        $asArray = $result->toArray();
        $this->assertSame('character', $asArray['actor']['type']);
        $this->assertSame((int) $actorCharacter->id, $asArray['actor']['character_id']);
        $this->assertSame('character', $asArray['target']['type']);
        $this->assertSame((int) $targetCharacter->id, $asArray['target']['character_id']);

        $this->assertTrue($asArray['attack']['is_success']);
        $this->assertSame(25, $asArray['attack']['kept_roll']);
        $this->assertSame(25, $asArray['attack']['total']);

        $this->assertTrue($asArray['defense']['attempted']);
        $this->assertFalse($asArray['defense']['is_success']);
        $this->assertSame(95, $asArray['defense']['kept_roll']);

        $this->assertTrue($asArray['outcome']['attack_hit']);
        $this->assertFalse($asArray['outcome']['defense_prevented_hit']);
        $this->assertSame(12, $asArray['outcome']['raw_damage']);
        $this->assertSame(3, $asArray['outcome']['armor_protection']);
        $this->assertSame(9, $asArray['outcome']['effective_damage']);
        $this->assertSame(-9, $asArray['outcome']['applied_le_delta']);
        $this->assertSame(11, $asArray['outcome']['resulting_le_current']);
        $this->assertSame(20, $asArray['outcome']['resulting_le_max']);
    }

    public function test_it_does_not_attempt_defense_when_attack_misses(): void
    {
        [$owner, $campaign, $scene] = $this->campaignContext();
        $participant = User::factory()->create();
        $this->grantPlayerMembership($campaign, $participant, $owner);

        $actorCharacter = $this->characterInCampaignWorld($owner, $campaign->world_id);
        $targetCharacter = $this->characterInCampaignWorld($participant, $campaign->world_id, [
            'le_max' => 17,
            'le_current' => 17,
        ]);

        $service = $this->makeService([96]);
        $result = $service->resolveSingleAction(new CombatActionInput(
            campaign: $campaign,
            scene: $scene,
            actor: CombatActor::character($actorCharacter),
            target: CombatTarget::character($targetCharacter),
            weaponName: null,
            attackTargetValue: 60,
            attackRollMode: DiceRoll::MODE_NORMAL,
            attackModifier: 0,
            defenseLabel: 'Parade',
            defenseTargetValue: 55,
            defenseRollMode: DiceRoll::MODE_NORMAL,
            defenseModifier: 0,
            damage: 10,
            armorProtection: 2,
        ));

        $targetCharacter->refresh();
        $this->assertSame(17, (int) $targetCharacter->le_current);

        $asArray = $result->toArray();
        $this->assertFalse($asArray['attack']['is_success']);
        $this->assertFalse($asArray['defense']['attempted']);
        $this->assertSame([], $asArray['defense']['rolls']);
        $this->assertNull($asArray['defense']['is_success']);
        $this->assertSame(0, $asArray['outcome']['effective_damage']);
        $this->assertSame(0, $asArray['outcome']['applied_le_delta']);
        $this->assertSame(17, $asArray['outcome']['resulting_le_current']);
    }

    public function test_it_prevents_damage_when_defense_succeeds(): void
    {
        [$owner, $campaign, $scene] = $this->campaignContext();
        $participant = User::factory()->create();
        $this->grantPlayerMembership($campaign, $participant, $owner);

        $actorCharacter = $this->characterInCampaignWorld($owner, $campaign->world_id);
        $targetCharacter = $this->characterInCampaignWorld($participant, $campaign->world_id, [
            'le_max' => 22,
            'le_current' => 22,
            'armors' => [[
                'name' => 'Leder',
                'protection' => 2,
                'equipped' => true,
            ]],
        ]);

        $service = $this->makeService([30, 20]);
        $result = $service->resolveSingleAction(new CombatActionInput(
            campaign: $campaign,
            scene: $scene,
            actor: CombatActor::character($actorCharacter),
            target: CombatTarget::character($targetCharacter),
            weaponName: 'Speer',
            attackTargetValue: 60,
            attackRollMode: DiceRoll::MODE_NORMAL,
            attackModifier: 0,
            defenseLabel: 'Parade',
            defenseTargetValue: 40,
            defenseRollMode: DiceRoll::MODE_NORMAL,
            defenseModifier: 0,
            damage: 11,
            armorProtection: null,
        ));

        $targetCharacter->refresh();
        $this->assertSame(22, (int) $targetCharacter->le_current);

        $asArray = $result->toArray();
        $this->assertTrue($asArray['attack']['is_success']);
        $this->assertTrue($asArray['defense']['attempted']);
        $this->assertTrue($asArray['defense']['is_success']);
        $this->assertTrue($asArray['outcome']['defense_prevented_hit']);
        $this->assertSame(0, $asArray['outcome']['effective_damage']);
        $this->assertSame(0, $asArray['outcome']['applied_le_delta']);
        $this->assertSame(22, $asArray['outcome']['resulting_le_current']);
    }

    public function test_it_resolves_npc_target_without_persisting_character_state(): void
    {
        [$owner, $campaign, $scene] = $this->campaignContext();
        $actorCharacter = $this->characterInCampaignWorld($owner, $campaign->world_id, [
            'name' => 'Raven',
        ]);

        $service = $this->makeService([20]);
        $result = $service->resolveSingleAction(new CombatActionInput(
            campaign: $campaign,
            scene: $scene,
            actor: CombatActor::character($actorCharacter),
            target: CombatTarget::npc('Aschewolf', [
                'le_current' => 18,
                'le_max' => 20,
                'armor_rs' => 2,
            ]),
            weaponName: 'Messer',
            attackTargetValue: 80,
            attackRollMode: DiceRoll::MODE_NORMAL,
            attackModifier: 0,
            defenseLabel: null,
            defenseTargetValue: null,
            defenseRollMode: DiceRoll::MODE_NORMAL,
            defenseModifier: 0,
            damage: 6,
            armorProtection: null,
        ));

        $asArray = $result->toArray();
        $this->assertSame('npc', $asArray['target']['type']);
        $this->assertNull($asArray['target']['character_id']);
        $this->assertSame(4, $asArray['outcome']['effective_damage']);
        $this->assertSame(0, $asArray['outcome']['applied_le_delta']);
        $this->assertSame(14, $asArray['outcome']['resulting_le_current']);
        $this->assertSame(20, $asArray['outcome']['resulting_le_max']);
        $this->assertSame(14, $asArray['snapshots']['target_snapshot_after']['le_current']);
        $this->assertSame(14, $asArray['snapshots']['target_snapshot_after']['resulting_le_current']);
    }

    public function test_it_reduces_damage_to_zero_when_armor_is_higher_than_raw_damage(): void
    {
        [$owner, $campaign, $scene] = $this->campaignContext();
        $participant = User::factory()->create();
        $this->grantPlayerMembership($campaign, $participant, $owner);

        $actorCharacter = $this->characterInCampaignWorld($owner, $campaign->world_id);
        $targetCharacter = $this->characterInCampaignWorld($participant, $campaign->world_id, [
            'le_max' => 18,
            'le_current' => 18,
            'armors' => [[
                'name' => 'Schuppenweste',
                'protection' => 8,
                'equipped' => true,
            ]],
        ]);

        $result = $this->makeService([20])->resolveSingleAction(new CombatActionInput(
            campaign: $campaign,
            scene: $scene,
            actor: CombatActor::character($actorCharacter),
            target: CombatTarget::character($targetCharacter),
            weaponName: 'Axt',
            attackTargetValue: 70,
            attackRollMode: DiceRoll::MODE_NORMAL,
            attackModifier: 0,
            defenseLabel: null,
            defenseTargetValue: null,
            defenseRollMode: DiceRoll::MODE_NORMAL,
            defenseModifier: 0,
            damage: 5,
            armorProtection: null,
        ));

        $targetCharacter->refresh();
        $this->assertSame(18, (int) $targetCharacter->le_current);

        $asArray = $result->toArray();
        $this->assertTrue($asArray['attack']['is_success']);
        $this->assertFalse($asArray['defense']['attempted']);
        $this->assertSame(8, $asArray['outcome']['armor_protection']);
        $this->assertSame(0, $asArray['outcome']['effective_damage']);
        $this->assertSame(0, $asArray['outcome']['applied_le_delta']);
        $this->assertSame(18, $asArray['outcome']['resulting_le_current']);
        $this->assertSame(18, $asArray['outcome']['resulting_le_max']);
    }

    public function test_it_clamps_character_le_to_zero_when_effective_damage_exceeds_current_le(): void
    {
        [$owner, $campaign, $scene] = $this->campaignContext();
        $participant = User::factory()->create();
        $this->grantPlayerMembership($campaign, $participant, $owner);

        $actorCharacter = $this->characterInCampaignWorld($owner, $campaign->world_id);
        $targetCharacter = $this->characterInCampaignWorld($participant, $campaign->world_id, [
            'le_max' => 20,
            'le_current' => 4,
            'armors' => [],
        ]);

        $result = $this->makeService([10])->resolveSingleAction(new CombatActionInput(
            campaign: $campaign,
            scene: $scene,
            actor: CombatActor::character($actorCharacter),
            target: CombatTarget::character($targetCharacter),
            weaponName: 'Hammer',
            attackTargetValue: 80,
            attackRollMode: DiceRoll::MODE_NORMAL,
            attackModifier: 0,
            defenseLabel: null,
            defenseTargetValue: null,
            defenseRollMode: DiceRoll::MODE_NORMAL,
            defenseModifier: 0,
            damage: 12,
            armorProtection: 0,
        ));

        $targetCharacter->refresh();
        $this->assertSame(0, (int) $targetCharacter->le_current);

        $asArray = $result->toArray();
        $this->assertSame(12, $asArray['outcome']['effective_damage']);
        $this->assertSame(-4, $asArray['outcome']['applied_le_delta']);
        $this->assertSame(0, $asArray['outcome']['resulting_le_current']);
        $this->assertSame(20, $asArray['outcome']['resulting_le_max']);
    }

    public function test_it_throws_invariant_violation_for_scene_campaign_mismatch(): void
    {
        [$owner, $campaign, $scene] = $this->campaignContext();
        $otherCampaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'world_id' => $campaign->world_id,
            'status' => 'active',
            'is_public' => true,
        ]);

        $actorCharacter = $this->characterInCampaignWorld($owner, $campaign->world_id);

        $this->expectException(CombatInvariantViolationException::class);
        try {
            $this->makeService([10])->resolveSingleAction(new CombatActionInput(
                campaign: $otherCampaign,
                scene: $scene,
                actor: CombatActor::character($actorCharacter),
                target: CombatTarget::npc('Bandit'),
                weaponName: null,
                attackTargetValue: 55,
                attackRollMode: DiceRoll::MODE_NORMAL,
                attackModifier: 0,
                defenseLabel: null,
                defenseTargetValue: null,
                defenseRollMode: DiceRoll::MODE_NORMAL,
                defenseModifier: 0,
                damage: 5,
                armorProtection: 0,
            ));
        } catch (CombatInvariantViolationException $exception) {
            $this->assertSame('scene_campaign_mismatch', $exception->reason());
            $this->assertSame('scene', $exception->field());

            throw $exception;
        }
    }

    public function test_it_throws_invariant_violation_for_target_world_mismatch(): void
    {
        [$owner, $campaign, $scene] = $this->campaignContext();
        $participant = User::factory()->create();
        $this->grantPlayerMembership($campaign, $participant, $owner);

        $actorCharacter = $this->characterInCampaignWorld($owner, $campaign->world_id);
        $otherWorld = World::factory()->create();
        $targetCharacter = $this->characterInCampaignWorld($participant, $otherWorld->id, [
            'le_max' => 30,
            'le_current' => 30,
        ]);

        $this->expectException(CombatInvariantViolationException::class);
        try {
            $this->makeService([20])->resolveSingleAction(new CombatActionInput(
                campaign: $campaign,
                scene: $scene,
                actor: CombatActor::character($actorCharacter),
                target: CombatTarget::character($targetCharacter),
                weaponName: null,
                attackTargetValue: 60,
                attackRollMode: DiceRoll::MODE_NORMAL,
                attackModifier: 0,
                defenseLabel: null,
                defenseTargetValue: null,
                defenseRollMode: DiceRoll::MODE_NORMAL,
                defenseModifier: 0,
                damage: 4,
                armorProtection: 0,
            ));
        } catch (CombatInvariantViolationException $exception) {
            $this->assertSame('target_character_world_mismatch', $exception->reason());
            $this->assertSame('target', $exception->field());

            throw $exception;
        }
    }

    public function test_it_falls_back_to_normal_roll_mode_for_unknown_attack_mode(): void
    {
        [$owner, $campaign, $scene] = $this->campaignContext();
        $actorCharacter = $this->characterInCampaignWorld($owner, $campaign->world_id);

        $result = $this->makeService([40])->resolveSingleAction(new CombatActionInput(
            campaign: $campaign,
            scene: $scene,
            actor: CombatActor::character($actorCharacter),
            target: CombatTarget::npc('Uebungspuppe'),
            weaponName: null,
            attackTargetValue: 55,
            attackRollMode: 'unsupported-mode',
            attackModifier: 0,
            defenseLabel: null,
            defenseTargetValue: null,
            defenseRollMode: DiceRoll::MODE_NORMAL,
            defenseModifier: 0,
            damage: 1,
            armorProtection: 0,
        ));

        $this->assertSame(DiceRoll::MODE_NORMAL, $result->toArray()['attack']['roll_mode']);
    }

    private function makeService(array $rolls): CombatService
    {
        return new CombatService(
            probeRoller: new ProbeRoller($this->sequenceGenerator($rolls)),
            campaignParticipantResolver: app(CampaignParticipantResolver::class),
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
