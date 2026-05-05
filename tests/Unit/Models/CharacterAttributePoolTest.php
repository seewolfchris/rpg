<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Character;
use App\Models\World;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CharacterAttributePoolTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_value_falls_back_to_effective_max_when_current_is_null(): void
    {
        $character = Character::factory()->create([
            ...$this->baseAttributeState(),
            'species' => 'elf',
            'in' => 40,
            'in_current' => null,
        ]);

        $pool = $character->attributePool('in');

        $this->assertSame(50, $character->effectiveAttributeMax('in'));
        $this->assertSame(50, $character->currentAttributeValue('in'));
        $this->assertSame(50, (int) $pool['max']);
        $this->assertSame(50, (int) $pool['current']);
        $this->assertFalse((bool) $pool['is_reduced']);
        $this->assertTrue((bool) $pool['is_modified']);
    }

    public function test_current_value_uses_reduced_stored_value_when_below_maximum(): void
    {
        $character = Character::factory()->create([
            ...$this->baseAttributeState(),
            'species' => 'mensch',
            'mu' => 40,
            'mu_current' => 31,
        ]);

        $pool = $character->attributePool('mu');

        $this->assertSame(40, $character->effectiveAttributeMax('mu'));
        $this->assertSame(31, $character->currentAttributeValue('mu'));
        $this->assertSame(31, (int) $pool['current']);
        $this->assertTrue((bool) $pool['is_reduced']);
        $this->assertFalse((bool) $pool['is_modified']);
    }

    public function test_current_value_is_clamped_to_effective_maximum_when_too_high(): void
    {
        $character = Character::factory()->create([
            ...$this->baseAttributeState(),
            'species' => 'elf',
            'kk' => 40,
            'kk_current' => 99,
        ]);

        $pool = $character->attributePool('kk');

        $this->assertSame(35, $character->effectiveAttributeMax('kk'));
        $this->assertSame(35, $character->currentAttributeValue('kk'));
        $this->assertSame(35, (int) $pool['current']);
        $this->assertFalse((bool) $pool['is_reduced']);
    }

    public function test_current_value_is_clamped_to_zero_for_negative_runtime_values(): void
    {
        $character = Character::factory()->make([
            ...$this->baseAttributeState(),
            'world_id' => World::resolveDefaultId(),
            'species' => 'mensch',
            'mu' => 40,
        ]);
        $character->mu_current = -12;

        $this->assertSame(0, $character->currentAttributeValue('mu'));
        $this->assertTrue((bool) $character->attributePool('mu')['is_reduced']);
    }

    /**
     * @return array<string, int|string|null>
     */
    private function baseAttributeState(): array
    {
        return [
            'world_id' => World::resolveDefaultId(),
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
            'mu_current' => null,
            'kl_current' => null,
            'in_current' => null,
            'ch_current' => null,
            'ff_current' => null,
            'ge_current' => null,
            'ko_current' => null,
            'kk_current' => null,
        ];
    }
}
