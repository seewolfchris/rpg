<?php

declare(strict_types=1);

namespace App\Domain\Magic\Data;

use App\Models\Campaign;
use App\Models\DiceRoll;
use App\Models\Scene;

final readonly class MagicActionInput
{
    public function __construct(
        public Campaign $campaign,
        public Scene $scene,
        public MagicActor $actor,
        public MagicTarget $target,
        public string $spellName,
        public int $spellTargetValue,
        public string $spellRollMode = DiceRoll::MODE_NORMAL,
        public int $spellModifier = 0,
        public int $aeCost = 0,
        public ?string $defenseLabel = null,
        public ?int $defenseTargetValue = null,
        public string $defenseRollMode = DiceRoll::MODE_NORMAL,
        public int $defenseModifier = 0,
        public string $effectType = 'narrative',
        public int $effectAmount = 0,
        public ?string $targetAttributeKey = null,
        public ?string $intentText = null,
        public ?string $resolutionNote = null,
    ) {}
}
