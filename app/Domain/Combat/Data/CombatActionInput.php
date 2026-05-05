<?php

declare(strict_types=1);

namespace App\Domain\Combat\Data;

use App\Models\Campaign;
use App\Models\DiceRoll;
use App\Models\Scene;

final readonly class CombatActionInput
{
    public function __construct(
        public Campaign $campaign,
        public Scene $scene,
        public CombatActor $actor,
        public CombatTarget $target,
        public ?string $weaponName,
        public int $attackTargetValue,
        public string $attackRollMode = DiceRoll::MODE_NORMAL,
        public int $attackModifier = 0,
        public ?string $defenseLabel = null,
        public ?int $defenseTargetValue = null,
        public string $defenseRollMode = DiceRoll::MODE_NORMAL,
        public int $defenseModifier = 0,
        public int $damage = 0,
        public ?int $armorProtection = null,
        public ?string $intentText = null,
        public ?string $resolutionNote = null,
    ) {}
}
