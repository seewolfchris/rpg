<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Character;
use Tests\TestCase;

class CharacterWeaponPoolTest extends TestCase
{
    public function test_normalized_weapons_clamp_values_and_support_legacy_synonyms(): void
    {
        $character = new Character([
            'weapons' => [
                [
                    'name' => '  Langschwert ',
                    'attack' => 130,
                    'parry' => -4,
                    'damage' => 1200,
                    'equipped' => true,
                ],
                [
                    'name' => 'Kurzdolch',
                    'ang' => 57,
                    'par' => 41,
                    'tp' => 11,
                    'equipped' => false,
                ],
                [
                    'name' => 'Stab',
                    'attack' => 25,
                    'parry' => 28,
                    'damage' => 0,
                    'equipped' => false,
                ],
                [
                    'name' => '   ',
                    'attack' => 50,
                    'parry' => 50,
                    'damage' => 10,
                ],
            ],
        ]);

        $normalized = $character->normalizedWeapons();

        $this->assertCount(3, $normalized);
        $this->assertSame([
            'name' => 'Langschwert',
            'attack' => 100,
            'parry' => 0,
            'damage' => 999,
            'equipped' => true,
        ], $normalized[0]);
        $this->assertSame([
            'name' => 'Kurzdolch',
            'attack' => 57,
            'parry' => 41,
            'damage' => 11,
            'equipped' => false,
        ], $normalized[1]);
        $this->assertSame([
            'name' => 'Stab',
            'attack' => 25,
            'parry' => 28,
            'damage' => 0,
            'equipped' => false,
        ], $normalized[2]);
    }

    public function test_active_weapon_prefers_first_equipped_weapon(): void
    {
        $character = new Character([
            'weapons' => [
                ['name' => 'Axt', 'attack' => 48, 'parry' => 30, 'damage' => 12, 'equipped' => true],
                ['name' => 'Speer', 'attack' => 55, 'parry' => 37, 'damage' => 10, 'equipped' => true],
            ],
        ]);

        $activeWeapon = $character->activeWeapon();

        $this->assertNotNull($activeWeapon);
        $this->assertSame('Axt', $activeWeapon['name']);
        $this->assertSame(48, $character->activeWeaponAttackValue());
        $this->assertSame(30, $character->activeWeaponDefenseValue());
        $this->assertSame(12, $character->activeWeaponEffectValue());
        $this->assertSame('Axt', $character->activeWeaponName());
    }

    public function test_active_weapon_falls_back_to_first_valid_weapon_when_none_equipped(): void
    {
        $character = new Character([
            'weapons' => [
                ['name' => 'Messer', 'attack' => 42, 'parry' => 33, 'damage' => 7, 'equipped' => false],
                ['name' => 'Keule', 'attack' => 38, 'parry' => 20, 'damage' => 9, 'equipped' => false],
            ],
        ]);

        $activeWeapon = $character->activeWeapon();

        $this->assertNotNull($activeWeapon);
        $this->assertSame('Messer', $activeWeapon['name']);
    }

    public function test_active_weapon_helpers_return_null_without_valid_weapon(): void
    {
        $character = new Character([
            'weapons' => [
                ['name' => '   ', 'attack' => 42, 'parry' => 33, 'damage' => 7],
            ],
        ]);

        $this->assertSame([], $character->normalizedWeapons());
        $this->assertNull($character->activeWeapon());
        $this->assertNull($character->activeWeaponName());
        $this->assertNull($character->activeWeaponAttackValue());
        $this->assertNull($character->activeWeaponDefenseValue());
        $this->assertNull($character->activeWeaponEffectValue());
    }

    public function test_normalized_weapons_keep_entry_without_damage_as_zero(): void
    {
        $character = new Character([
            'weapons' => [
                ['name' => 'Peitsche', 'attack' => 39, 'parry' => 22],
            ],
        ]);

        $normalized = $character->normalizedWeapons();

        $this->assertCount(1, $normalized);
        $this->assertSame(0, $normalized[0]['damage']);
    }
}
