<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Magic;

use App\Domain\Campaign\CampaignParticipantResolver;
use App\Domain\Magic\Data\MagicActionInput;
use App\Domain\Magic\Data\MagicActor;
use App\Domain\Magic\Data\MagicTarget;
use App\Domain\Magic\Exceptions\MagicInvariantViolationException;
use App\Domain\Magic\MagicService;
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

class MagicServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_applies_le_damage_and_pays_ae_cost_for_character_target(): void
    {
        [$owner, $campaign, $scene] = $this->campaignContext();
        $participant = User::factory()->create();
        $this->grantPlayerMembership($campaign, $participant, $owner);

        $actorCharacter = $this->characterInCampaignWorld($owner, $campaign->world_id, [
            'name' => 'Seris',
            'ae_max' => 12,
            'ae_current' => 10,
        ]);
        $targetCharacter = $this->characterInCampaignWorld($participant, $campaign->world_id, [
            'name' => 'Toren',
            'le_max' => 20,
            'le_current' => 20,
        ]);

        $result = $this->makeService([22])->resolveSingleAction(new MagicActionInput(
            campaign: $campaign,
            scene: $scene,
            actor: MagicActor::character($actorCharacter),
            target: MagicTarget::character($targetCharacter),
            spellName: 'Aetherklinge',
            spellTargetValue: 60,
            spellRollMode: DiceRoll::MODE_NORMAL,
            spellModifier: 0,
            aeCost: 3,
            defenseLabel: null,
            defenseTargetValue: null,
            defenseRollMode: DiceRoll::MODE_NORMAL,
            defenseModifier: 0,
            effectType: MagicService::EFFECT_LE_DAMAGE,
            effectAmount: 7,
        ));

        $actorCharacter->refresh();
        $targetCharacter->refresh();

        $this->assertSame(7, (int) $actorCharacter->ae_current);
        $this->assertSame(13, (int) $targetCharacter->le_current);

        $asArray = $result->toArray();
        $this->assertSame(-3, $asArray['ae_cost']['applied_ae_delta']);
        $this->assertSame(7, $asArray['ae_cost']['resulting_actor_ae_current']);
        $this->assertTrue($asArray['spell_roll']['is_success']);
        $this->assertTrue($asArray['effect']['was_applied']);
        $this->assertSame(-7, $asArray['effect']['applied_le_delta']);
        $this->assertSame(13, $asArray['effect']['resulting_le_current']);
    }

    public function test_it_pays_ae_cost_even_when_spell_misses_without_applying_effect(): void
    {
        [$owner, $campaign, $scene] = $this->campaignContext();
        $participant = User::factory()->create();
        $this->grantPlayerMembership($campaign, $participant, $owner);

        $actorCharacter = $this->characterInCampaignWorld($owner, $campaign->world_id, [
            'ae_max' => 8,
            'ae_current' => 5,
        ]);
        $targetCharacter = $this->characterInCampaignWorld($participant, $campaign->world_id, [
            'le_max' => 19,
            'le_current' => 19,
        ]);

        $result = $this->makeService([95])->resolveSingleAction(new MagicActionInput(
            campaign: $campaign,
            scene: $scene,
            actor: MagicActor::character($actorCharacter),
            target: MagicTarget::character($targetCharacter),
            spellName: 'Flammenstoss',
            spellTargetValue: 60,
            spellRollMode: DiceRoll::MODE_NORMAL,
            spellModifier: 0,
            aeCost: 2,
            defenseLabel: 'Magieabwehr',
            defenseTargetValue: 55,
            defenseRollMode: DiceRoll::MODE_NORMAL,
            defenseModifier: 0,
            effectType: MagicService::EFFECT_LE_DAMAGE,
            effectAmount: 9,
        ));

        $actorCharacter->refresh();
        $targetCharacter->refresh();

        $this->assertSame(3, (int) $actorCharacter->ae_current);
        $this->assertSame(19, (int) $targetCharacter->le_current);

        $asArray = $result->toArray();
        $this->assertFalse($asArray['spell_roll']['is_success']);
        $this->assertFalse($asArray['defense']['attempted']);
        $this->assertFalse($asArray['effect']['was_applied']);
    }

    public function test_it_prevents_effect_when_magic_defense_succeeds(): void
    {
        [$owner, $campaign, $scene] = $this->campaignContext();
        $participant = User::factory()->create();
        $this->grantPlayerMembership($campaign, $participant, $owner);

        $actorCharacter = $this->characterInCampaignWorld($owner, $campaign->world_id, [
            'ae_max' => 10,
            'ae_current' => 10,
        ]);
        $targetCharacter = $this->characterInCampaignWorld($participant, $campaign->world_id, [
            'le_max' => 18,
            'le_current' => 18,
        ]);

        $result = $this->makeService([20, 15])->resolveSingleAction(new MagicActionInput(
            campaign: $campaign,
            scene: $scene,
            actor: MagicActor::character($actorCharacter),
            target: MagicTarget::character($targetCharacter),
            spellName: 'Schattenlanze',
            spellTargetValue: 70,
            spellRollMode: DiceRoll::MODE_NORMAL,
            spellModifier: 0,
            aeCost: 2,
            defenseLabel: 'Magieabwehr',
            defenseTargetValue: 40,
            defenseRollMode: DiceRoll::MODE_NORMAL,
            defenseModifier: 0,
            effectType: MagicService::EFFECT_LE_DAMAGE,
            effectAmount: 8,
        ));

        $actorCharacter->refresh();
        $targetCharacter->refresh();

        $this->assertSame(8, (int) $actorCharacter->ae_current);
        $this->assertSame(18, (int) $targetCharacter->le_current);

        $asArray = $result->toArray();
        $this->assertTrue($asArray['spell_roll']['is_success']);
        $this->assertTrue($asArray['defense']['attempted']);
        $this->assertTrue($asArray['defense']['is_success']);
        $this->assertFalse($asArray['effect']['was_applied']);
    }

    public function test_it_throws_when_character_has_insufficient_ae(): void
    {
        [$owner, $campaign, $scene] = $this->campaignContext();
        $participant = User::factory()->create();
        $this->grantPlayerMembership($campaign, $participant, $owner);

        $actorCharacter = $this->characterInCampaignWorld($owner, $campaign->world_id, [
            'ae_max' => 5,
            'ae_current' => 1,
        ]);
        $targetCharacter = $this->characterInCampaignWorld($participant, $campaign->world_id, [
            'le_max' => 20,
            'le_current' => 20,
        ]);

        $this->expectException(MagicInvariantViolationException::class);

        try {
            $this->makeService([10])->resolveSingleAction(new MagicActionInput(
                campaign: $campaign,
                scene: $scene,
                actor: MagicActor::character($actorCharacter),
                target: MagicTarget::character($targetCharacter),
                spellName: 'Aestrahl',
                spellTargetValue: 70,
                spellRollMode: DiceRoll::MODE_NORMAL,
                spellModifier: 0,
                aeCost: 3,
                effectType: MagicService::EFFECT_LE_DAMAGE,
                effectAmount: 6,
            ));
        } catch (MagicInvariantViolationException $exception) {
            $this->assertSame('insufficient_astral_energy', $exception->reason());
            $this->assertSame('ae_cost', $exception->field());

            throw $exception;
        } finally {
            $actorCharacter->refresh();
            $targetCharacter->refresh();
            $this->assertSame(1, (int) $actorCharacter->ae_current);
            $this->assertSame(20, (int) $targetCharacter->le_current);
        }
    }

    public function test_it_applies_ae_damage_to_character_target(): void
    {
        [$owner, $campaign, $scene] = $this->campaignContext();
        $participant = User::factory()->create();
        $this->grantPlayerMembership($campaign, $participant, $owner);

        $actorCharacter = $this->characterInCampaignWorld($owner, $campaign->world_id, [
            'ae_max' => 10,
            'ae_current' => 10,
        ]);
        $targetCharacter = $this->characterInCampaignWorld($participant, $campaign->world_id, [
            'ae_max' => 12,
            'ae_current' => 10,
        ]);

        $result = $this->makeService([18])->resolveSingleAction(new MagicActionInput(
            campaign: $campaign,
            scene: $scene,
            actor: MagicActor::character($actorCharacter),
            target: MagicTarget::character($targetCharacter),
            spellName: 'Leersog',
            spellTargetValue: 75,
            spellRollMode: DiceRoll::MODE_NORMAL,
            spellModifier: 0,
            aeCost: 1,
            effectType: MagicService::EFFECT_AE_DAMAGE,
            effectAmount: 4,
        ));

        $actorCharacter->refresh();
        $targetCharacter->refresh();

        $this->assertSame(9, (int) $actorCharacter->ae_current);
        $this->assertSame(6, (int) $targetCharacter->ae_current);

        $this->assertSame(-4, $result->toArray()['effect']['applied_ae_delta']);
    }

    public function test_it_applies_effect_when_magic_defense_fails(): void
    {
        [$owner, $campaign, $scene] = $this->campaignContext();
        $participant = User::factory()->create();
        $this->grantPlayerMembership($campaign, $participant, $owner);

        $actorCharacter = $this->characterInCampaignWorld($owner, $campaign->world_id, [
            'ae_max' => 10,
            'ae_current' => 10,
        ]);
        $targetCharacter = $this->characterInCampaignWorld($participant, $campaign->world_id, [
            'le_max' => 18,
            'le_current' => 18,
        ]);

        $result = $this->makeService([20, 95])->resolveSingleAction(new MagicActionInput(
            campaign: $campaign,
            scene: $scene,
            actor: MagicActor::character($actorCharacter),
            target: MagicTarget::character($targetCharacter),
            spellName: 'Schattenlanze',
            spellTargetValue: 70,
            spellRollMode: DiceRoll::MODE_NORMAL,
            spellModifier: 0,
            aeCost: 2,
            defenseLabel: 'Magieabwehr',
            defenseTargetValue: 40,
            defenseRollMode: DiceRoll::MODE_NORMAL,
            defenseModifier: 0,
            effectType: MagicService::EFFECT_LE_DAMAGE,
            effectAmount: 8,
        ));

        $actorCharacter->refresh();
        $targetCharacter->refresh();

        $this->assertSame(8, (int) $actorCharacter->ae_current);
        $this->assertSame(10, (int) $targetCharacter->le_current);

        $asArray = $result->toArray();
        $this->assertTrue($asArray['spell_roll']['is_success']);
        $this->assertTrue($asArray['defense']['attempted']);
        $this->assertFalse($asArray['defense']['is_success']);
        $this->assertTrue($asArray['effect']['was_applied']);
        $this->assertSame(-8, $asArray['effect']['applied_le_delta']);
    }

    public function test_it_clamps_le_damage_to_zero_for_character_target(): void
    {
        [$owner, $campaign, $scene] = $this->campaignContext();
        $participant = User::factory()->create();
        $this->grantPlayerMembership($campaign, $participant, $owner);

        $actorCharacter = $this->characterInCampaignWorld($owner, $campaign->world_id, [
            'ae_max' => 8,
            'ae_current' => 8,
        ]);
        $targetCharacter = $this->characterInCampaignWorld($participant, $campaign->world_id, [
            'le_max' => 20,
            'le_current' => 3,
        ]);

        $result = $this->makeService([10])->resolveSingleAction(new MagicActionInput(
            campaign: $campaign,
            scene: $scene,
            actor: MagicActor::character($actorCharacter),
            target: MagicTarget::character($targetCharacter),
            spellName: 'Schmerzlied',
            spellTargetValue: 90,
            spellRollMode: DiceRoll::MODE_NORMAL,
            spellModifier: 0,
            aeCost: 1,
            effectType: MagicService::EFFECT_LE_DAMAGE,
            effectAmount: 9,
        ));

        $targetCharacter->refresh();

        $this->assertSame(0, (int) $targetCharacter->le_current);
        $this->assertSame(-3, $result->toArray()['effect']['applied_le_delta']);
        $this->assertSame(0, $result->toArray()['effect']['resulting_le_current']);
    }

    public function test_it_clamps_le_heal_to_le_maximum(): void
    {
        [$owner, $campaign, $scene] = $this->campaignContext();
        $participant = User::factory()->create();
        $this->grantPlayerMembership($campaign, $participant, $owner);

        $actorCharacter = $this->characterInCampaignWorld($owner, $campaign->world_id, [
            'ae_max' => 8,
            'ae_current' => 8,
        ]);
        $targetCharacter = $this->characterInCampaignWorld($participant, $campaign->world_id, [
            'le_max' => 20,
            'le_current' => 18,
        ]);

        $result = $this->makeService([10])->resolveSingleAction(new MagicActionInput(
            campaign: $campaign,
            scene: $scene,
            actor: MagicActor::character($actorCharacter),
            target: MagicTarget::character($targetCharacter),
            spellName: 'Wundsegen',
            spellTargetValue: 90,
            spellRollMode: DiceRoll::MODE_NORMAL,
            spellModifier: 0,
            aeCost: 1,
            effectType: MagicService::EFFECT_LE_HEAL,
            effectAmount: 9,
        ));

        $targetCharacter->refresh();

        $this->assertSame(20, (int) $targetCharacter->le_current);
        $this->assertSame(2, $result->toArray()['effect']['applied_le_delta']);
        $this->assertSame(20, $result->toArray()['effect']['resulting_le_current']);
    }

    public function test_it_clamps_ae_damage_to_zero_for_character_target(): void
    {
        [$owner, $campaign, $scene] = $this->campaignContext();
        $participant = User::factory()->create();
        $this->grantPlayerMembership($campaign, $participant, $owner);

        $actorCharacter = $this->characterInCampaignWorld($owner, $campaign->world_id, [
            'ae_max' => 9,
            'ae_current' => 9,
        ]);
        $targetCharacter = $this->characterInCampaignWorld($participant, $campaign->world_id, [
            'ae_max' => 12,
            'ae_current' => 3,
        ]);

        $result = $this->makeService([10])->resolveSingleAction(new MagicActionInput(
            campaign: $campaign,
            scene: $scene,
            actor: MagicActor::character($actorCharacter),
            target: MagicTarget::character($targetCharacter),
            spellName: 'Aetherbruch',
            spellTargetValue: 90,
            spellRollMode: DiceRoll::MODE_NORMAL,
            spellModifier: 0,
            aeCost: 1,
            effectType: MagicService::EFFECT_AE_DAMAGE,
            effectAmount: 10,
        ));

        $targetCharacter->refresh();

        $this->assertSame(0, (int) $targetCharacter->ae_current);
        $this->assertSame(-3, $result->toArray()['effect']['applied_ae_delta']);
        $this->assertSame(0, $result->toArray()['effect']['resulting_ae_current']);
    }

    public function test_it_applies_negative_attribute_delta_from_null_current(): void
    {
        [$owner, $campaign, $scene] = $this->campaignContext();
        $participant = User::factory()->create();
        $this->grantPlayerMembership($campaign, $participant, $owner);

        $actorCharacter = $this->characterInCampaignWorld($owner, $campaign->world_id, [
            'ae_max' => 6,
            'ae_current' => 6,
        ]);
        $targetCharacter = $this->characterInCampaignWorld($participant, $campaign->world_id, [
            'mu' => 40,
            'mu_current' => null,
        ]);

        $result = $this->makeService([12])->resolveSingleAction(new MagicActionInput(
            campaign: $campaign,
            scene: $scene,
            actor: MagicActor::character($actorCharacter),
            target: MagicTarget::character($targetCharacter),
            spellName: 'Mutbruch',
            spellTargetValue: 80,
            spellRollMode: DiceRoll::MODE_NORMAL,
            spellModifier: 0,
            aeCost: 1,
            effectType: MagicService::EFFECT_ATTRIBUTE_DELTA,
            effectAmount: -15,
            targetAttributeKey: 'mu',
        ));

        $targetCharacter->refresh();
        $this->assertSame(25, (int) $targetCharacter->mu_current);

        $asArray = $result->toArray();
        $this->assertSame('mu', $asArray['effect']['target_attribute_key']);
        $this->assertSame(-15, $asArray['effect']['applied_attribute_delta']);
        $this->assertSame(25, $asArray['effect']['resulting_attribute_current']);
        $this->assertSame(40, $asArray['effect']['resulting_attribute_max']);
    }

    public function test_it_clamps_attribute_delta_to_effective_maximum(): void
    {
        [$owner, $campaign, $scene] = $this->campaignContext();
        $participant = User::factory()->create();
        $this->grantPlayerMembership($campaign, $participant, $owner);

        $actorCharacter = $this->characterInCampaignWorld($owner, $campaign->world_id, [
            'ae_max' => 6,
            'ae_current' => 6,
        ]);
        $targetCharacter = $this->characterInCampaignWorld($participant, $campaign->world_id, [
            'species' => 'elf',
            'in' => 40,
            'in_current' => 45,
        ]);

        $result = $this->makeService([11])->resolveSingleAction(new MagicActionInput(
            campaign: $campaign,
            scene: $scene,
            actor: MagicActor::character($actorCharacter),
            target: MagicTarget::character($targetCharacter),
            spellName: 'Inspiration',
            spellTargetValue: 85,
            spellRollMode: DiceRoll::MODE_NORMAL,
            spellModifier: 0,
            aeCost: 0,
            effectType: MagicService::EFFECT_ATTRIBUTE_DELTA,
            effectAmount: 20,
            targetAttributeKey: 'in',
        ));

        $targetCharacter->refresh();
        $this->assertSame(50, (int) $targetCharacter->in_current);

        $asArray = $result->toArray();
        $this->assertSame(5, $asArray['effect']['applied_attribute_delta']);
        $this->assertSame(50, $asArray['effect']['resulting_attribute_current']);
        $this->assertSame(50, $asArray['effect']['resulting_attribute_max']);
    }

    public function test_it_resolves_npc_target_without_persistence_and_updates_snapshot(): void
    {
        [$owner, $campaign, $scene] = $this->campaignContext();
        $actorCharacter = $this->characterInCampaignWorld($owner, $campaign->world_id, [
            'name' => 'Vaelis',
            'ae_max' => 8,
            'ae_current' => 8,
        ]);

        $result = $this->makeService([25])->resolveSingleAction(new MagicActionInput(
            campaign: $campaign,
            scene: $scene,
            actor: MagicActor::character($actorCharacter),
            target: MagicTarget::npc('Schattenhund', [
                'le_current' => 18,
                'le_max' => 20,
            ]),
            spellName: 'Funkenlanze',
            spellTargetValue: 80,
            spellRollMode: DiceRoll::MODE_NORMAL,
            spellModifier: 0,
            aeCost: 2,
            effectType: MagicService::EFFECT_LE_DAMAGE,
            effectAmount: 8,
        ));

        $actorCharacter->refresh();
        $this->assertSame(6, (int) $actorCharacter->ae_current);

        $asArray = $result->toArray();
        $this->assertSame('npc', $asArray['target']['type']);
        $this->assertSame(-8, $asArray['effect']['applied_le_delta']);
        $this->assertSame(10, $asArray['effect']['resulting_le_current']);
        $this->assertSame(10, $asArray['snapshots']['target_snapshot_after']['le_current']);
    }

    public function test_it_updates_npc_caster_snapshot_ae_when_present(): void
    {
        [$owner, $campaign, $scene] = $this->campaignContext();
        $targetCharacter = $this->characterInCampaignWorld($owner, $campaign->world_id, [
            'le_max' => 22,
            'le_current' => 22,
        ]);

        $result = $this->makeService([20])->resolveSingleAction(new MagicActionInput(
            campaign: $campaign,
            scene: $scene,
            actor: MagicActor::npc('Hexer', [
                'ae_current' => 5,
                'ae_max' => 10,
            ]),
            target: MagicTarget::character($targetCharacter),
            spellName: 'Dornenwind',
            spellTargetValue: 90,
            spellRollMode: DiceRoll::MODE_NORMAL,
            spellModifier: 0,
            aeCost: 6,
            effectType: MagicService::EFFECT_NARRATIVE,
            effectAmount: 0,
        ));

        $targetCharacter->refresh();
        $this->assertSame(22, (int) $targetCharacter->le_current);

        $asArray = $result->toArray();
        $this->assertSame(-5, $asArray['ae_cost']['applied_ae_delta']);
        $this->assertSame(0, $asArray['ae_cost']['resulting_actor_ae_current']);
    }

    public function test_narrative_effect_does_not_mutate_character_target_resources(): void
    {
        [$owner, $campaign, $scene] = $this->campaignContext();
        $participant = User::factory()->create();
        $this->grantPlayerMembership($campaign, $participant, $owner);

        $actorCharacter = $this->characterInCampaignWorld($owner, $campaign->world_id, [
            'ae_max' => 10,
            'ae_current' => 7,
        ]);
        $targetCharacter = $this->characterInCampaignWorld($participant, $campaign->world_id, [
            'le_max' => 22,
            'le_current' => 15,
            'ae_max' => 6,
            'ae_current' => 4,
            'mu' => 40,
            'mu_current' => 31,
        ]);

        $result = $this->makeService([8])->resolveSingleAction(new MagicActionInput(
            campaign: $campaign,
            scene: $scene,
            actor: MagicActor::character($actorCharacter),
            target: MagicTarget::character($targetCharacter),
            spellName: 'Drohkulisse',
            spellTargetValue: 90,
            spellRollMode: DiceRoll::MODE_NORMAL,
            spellModifier: 0,
            aeCost: 2,
            effectType: MagicService::EFFECT_NARRATIVE,
            effectAmount: 0,
        ));

        $actorCharacter->refresh();
        $targetCharacter->refresh();

        $this->assertSame(5, (int) $actorCharacter->ae_current);
        $this->assertSame(15, (int) $targetCharacter->le_current);
        $this->assertSame(4, (int) $targetCharacter->ae_current);
        $this->assertSame(31, (int) $targetCharacter->mu_current);
        $this->assertTrue($result->toArray()['effect']['was_applied']);
    }

    public function test_it_throws_for_unknown_target_attribute_key_on_attribute_delta(): void
    {
        [$owner, $campaign, $scene] = $this->campaignContext();
        $participant = User::factory()->create();
        $this->grantPlayerMembership($campaign, $participant, $owner);

        $actorCharacter = $this->characterInCampaignWorld($owner, $campaign->world_id, [
            'ae_max' => 8,
            'ae_current' => 8,
        ]);
        $targetCharacter = $this->characterInCampaignWorld($participant, $campaign->world_id);

        $this->expectException(MagicInvariantViolationException::class);

        try {
            $this->makeService([12])->resolveSingleAction(new MagicActionInput(
                campaign: $campaign,
                scene: $scene,
                actor: MagicActor::character($actorCharacter),
                target: MagicTarget::character($targetCharacter),
                spellName: 'Verformung',
                spellTargetValue: 85,
                spellRollMode: DiceRoll::MODE_NORMAL,
                spellModifier: 0,
                aeCost: 1,
                effectType: MagicService::EFFECT_ATTRIBUTE_DELTA,
                effectAmount: -4,
                targetAttributeKey: 'xyz',
            ));
        } catch (MagicInvariantViolationException $exception) {
            $this->assertSame('target_attribute_key_invalid', $exception->reason());
            $this->assertSame('target_attribute_key', $exception->field());

            throw $exception;
        }
    }

    public function test_it_requires_target_attribute_key_for_attribute_delta(): void
    {
        [$owner, $campaign, $scene] = $this->campaignContext();
        $participant = User::factory()->create();
        $this->grantPlayerMembership($campaign, $participant, $owner);

        $actorCharacter = $this->characterInCampaignWorld($owner, $campaign->world_id, [
            'ae_max' => 8,
            'ae_current' => 8,
        ]);
        $targetCharacter = $this->characterInCampaignWorld($participant, $campaign->world_id);

        $this->expectException(MagicInvariantViolationException::class);

        try {
            $this->makeService([12])->resolveSingleAction(new MagicActionInput(
                campaign: $campaign,
                scene: $scene,
                actor: MagicActor::character($actorCharacter),
                target: MagicTarget::character($targetCharacter),
                spellName: 'Verformung',
                spellTargetValue: 85,
                spellRollMode: DiceRoll::MODE_NORMAL,
                spellModifier: 0,
                aeCost: 1,
                effectType: MagicService::EFFECT_ATTRIBUTE_DELTA,
                effectAmount: -4,
                targetAttributeKey: null,
            ));
        } catch (MagicInvariantViolationException $exception) {
            $this->assertSame('target_attribute_key_required', $exception->reason());
            $this->assertSame('target_attribute_key', $exception->field());

            throw $exception;
        }
    }

    public function test_it_throws_for_invalid_effect_type(): void
    {
        [$owner, $campaign, $scene] = $this->campaignContext();
        $participant = User::factory()->create();
        $this->grantPlayerMembership($campaign, $participant, $owner);

        $actorCharacter = $this->characterInCampaignWorld($owner, $campaign->world_id, [
            'ae_max' => 8,
            'ae_current' => 8,
        ]);
        $targetCharacter = $this->characterInCampaignWorld($participant, $campaign->world_id);

        $this->expectException(MagicInvariantViolationException::class);

        try {
            $this->makeService([12])->resolveSingleAction(new MagicActionInput(
                campaign: $campaign,
                scene: $scene,
                actor: MagicActor::character($actorCharacter),
                target: MagicTarget::character($targetCharacter),
                spellName: 'Chaosform',
                spellTargetValue: 85,
                spellRollMode: DiceRoll::MODE_NORMAL,
                spellModifier: 0,
                aeCost: 1,
                effectType: 'invalid_effect',
                effectAmount: 5,
            ));
        } catch (MagicInvariantViolationException $exception) {
            $this->assertSame('effect_type_invalid', $exception->reason());
            $this->assertSame('effect_type', $exception->field());

            throw $exception;
        }
    }

    public function test_spell_miss_does_not_consume_second_roll_for_defense(): void
    {
        [$owner, $campaign, $scene] = $this->campaignContext();
        $participant = User::factory()->create();
        $this->grantPlayerMembership($campaign, $participant, $owner);

        $actorCharacter = $this->characterInCampaignWorld($owner, $campaign->world_id, [
            'ae_max' => 8,
            'ae_current' => 8,
        ]);
        $targetCharacter = $this->characterInCampaignWorld($participant, $campaign->world_id, [
            'le_max' => 20,
            'le_current' => 20,
        ]);

        $calls = 0;
        $roller = new ProbeRoller(static function () use (&$calls): int {
            $calls++;

            if ($calls > 1) {
                throw new \RuntimeException('Defense roll should not execute on spell miss.');
            }

            return 95;
        });

        $service = new MagicService(
            probeRoller: $roller,
            campaignParticipantResolver: app(CampaignParticipantResolver::class),
        );

        $result = $service->resolveSingleAction(new MagicActionInput(
            campaign: $campaign,
            scene: $scene,
            actor: MagicActor::character($actorCharacter),
            target: MagicTarget::character($targetCharacter),
            spellName: 'Irrlicht',
            spellTargetValue: 60,
            spellRollMode: DiceRoll::MODE_NORMAL,
            spellModifier: 0,
            aeCost: 1,
            defenseLabel: 'Magieabwehr',
            defenseTargetValue: 50,
            defenseRollMode: DiceRoll::MODE_NORMAL,
            defenseModifier: 0,
            effectType: MagicService::EFFECT_LE_DAMAGE,
            effectAmount: 4,
        ));

        $actorCharacter->refresh();
        $targetCharacter->refresh();

        $this->assertSame(1, $calls);
        $this->assertFalse($result->toArray()['defense']['attempted']);
        $this->assertSame(7, (int) $actorCharacter->ae_current);
        $this->assertSame(20, (int) $targetCharacter->le_current);
    }

    public function test_it_falls_back_to_normal_roll_mode_for_unknown_modes(): void
    {
        [$owner, $campaign, $scene] = $this->campaignContext();
        $participant = User::factory()->create();
        $this->grantPlayerMembership($campaign, $participant, $owner);

        $actorCharacter = $this->characterInCampaignWorld($owner, $campaign->world_id, [
            'ae_max' => 10,
            'ae_current' => 10,
        ]);
        $targetCharacter = $this->characterInCampaignWorld($participant, $campaign->world_id, [
            'le_max' => 15,
            'le_current' => 15,
        ]);

        $result = $this->makeService([30, 80])->resolveSingleAction(new MagicActionInput(
            campaign: $campaign,
            scene: $scene,
            actor: MagicActor::character($actorCharacter),
            target: MagicTarget::character($targetCharacter),
            spellName: 'Pruefstrahl',
            spellTargetValue: 65,
            spellRollMode: 'unsupported-mode',
            spellModifier: 0,
            aeCost: 1,
            defenseLabel: 'Abwehr',
            defenseTargetValue: 50,
            defenseRollMode: 'unknown-mode',
            defenseModifier: 0,
            effectType: MagicService::EFFECT_LE_DAMAGE,
            effectAmount: 3,
        ));

        $asArray = $result->toArray();
        $this->assertSame(DiceRoll::MODE_NORMAL, $asArray['spell_roll']['roll_mode']);
        $this->assertSame(DiceRoll::MODE_NORMAL, $asArray['defense']['roll_mode']);
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

        $this->expectException(MagicInvariantViolationException::class);
        try {
            $this->makeService([25])->resolveSingleAction(new MagicActionInput(
                campaign: $otherCampaign,
                scene: $scene,
                actor: MagicActor::character($actorCharacter),
                target: MagicTarget::npc('Nebelrabe'),
                spellName: 'Nebelstich',
                spellTargetValue: 55,
                spellRollMode: DiceRoll::MODE_NORMAL,
                spellModifier: 0,
                aeCost: 1,
                effectType: MagicService::EFFECT_NARRATIVE,
                effectAmount: 0,
            ));
        } catch (MagicInvariantViolationException $exception) {
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

        $actorCharacter = $this->characterInCampaignWorld($owner, $campaign->world_id, [
            'ae_max' => 9,
            'ae_current' => 9,
        ]);

        $otherWorld = World::factory()->create();
        $targetCharacter = $this->characterInCampaignWorld($participant, $otherWorld->id, [
            'le_max' => 20,
            'le_current' => 20,
        ]);

        $this->expectException(MagicInvariantViolationException::class);
        try {
            $this->makeService([15])->resolveSingleAction(new MagicActionInput(
                campaign: $campaign,
                scene: $scene,
                actor: MagicActor::character($actorCharacter),
                target: MagicTarget::character($targetCharacter),
                spellName: 'Seelenriss',
                spellTargetValue: 70,
                spellRollMode: DiceRoll::MODE_NORMAL,
                spellModifier: 0,
                aeCost: 2,
                effectType: MagicService::EFFECT_LE_DAMAGE,
                effectAmount: 5,
            ));
        } catch (MagicInvariantViolationException $exception) {
            $this->assertSame('target_character_world_mismatch', $exception->reason());
            $this->assertSame('target', $exception->field());

            throw $exception;
        }
    }

    private function makeService(array $rolls): MagicService
    {
        return new MagicService(
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
            'mu_current' => null,
            'kl_current' => null,
            'in_current' => null,
            'ch_current' => null,
            'ff_current' => null,
            'ge_current' => null,
            'ko_current' => null,
            'kk_current' => null,
            'strength' => 40,
            'dexterity' => 40,
            'constitution' => 40,
            'intelligence' => 40,
            'wisdom' => 40,
            'charisma' => 40,
            'le_max' => 20,
            'le_current' => 20,
            'ae_max' => 10,
            'ae_current' => 10,
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
