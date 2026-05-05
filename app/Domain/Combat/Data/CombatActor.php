<?php

declare(strict_types=1);

namespace App\Domain\Combat\Data;

use App\Models\Character;

final readonly class CombatActor
{
    public const TYPE_CHARACTER = 'character';

    public const TYPE_NPC = 'npc';

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function __construct(
        public string $type,
        public ?Character $character = null,
        public ?string $name = null,
        public array $snapshot = [],
    ) {}

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public static function character(Character $character, ?string $name = null, array $snapshot = []): self
    {
        return new self(
            type: self::TYPE_CHARACTER,
            character: $character,
            name: $name,
            snapshot: $snapshot,
        );
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public static function npc(string $name, array $snapshot = []): self
    {
        return new self(
            type: self::TYPE_NPC,
            character: null,
            name: $name,
            snapshot: $snapshot,
        );
    }

    public function isCharacter(): bool
    {
        return $this->type === self::TYPE_CHARACTER;
    }

    public function isNpc(): bool
    {
        return $this->type === self::TYPE_NPC;
    }

    public function characterId(): ?int
    {
        return $this->character instanceof Character
            ? (int) $this->character->id
            : null;
    }

    public function resolvedName(): string
    {
        $name = trim((string) ($this->name ?? ''));

        if ($name !== '') {
            return $name;
        }

        return $this->character instanceof Character
            ? trim((string) $this->character->name)
            : '';
    }

    /**
     * @return array{
     *     type: string,
     *     character_id: int|null,
     *     name: string,
     *     snapshot: array<string, mixed>
     * }
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'character_id' => $this->characterId(),
            'name' => $this->resolvedName(),
            'snapshot' => $this->snapshot,
        ];
    }
}
