<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Character;

use App\Actions\Character\UpdateCharacterAction;
use App\Data\Character\UpdateCharacterInput;
use App\Models\Character;
use App\Models\User;
use App\Services\Character\AttributeNormalizer;
use App\Support\CharacterInventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UpdateCharacterActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_updates_character_and_logs_inventory_diff(): void
    {
        $user = User::factory()->create();
        $character = Character::factory()->create([
            'user_id' => $user->id,
            'name' => 'Alter Name',
            'inventory' => [
                ['name' => 'Alte Fackel', 'quantity' => 1, 'equipped' => false],
            ],
            'avatar_path' => null,
        ]);
        $payload = ['name' => 'Neuer Name'];

        $normalizedData = [
            'name' => 'Neuer Name',
            'inventory' => [
                ['name' => 'Neue Fackel', 'quantity' => 2, 'equipped' => false],
            ],
        ];
        $previousInventory = [
            ['name' => 'Alte Fackel', 'quantity' => 1, 'equipped' => false],
        ];
        $nextInventory = [
            ['name' => 'Neue Fackel', 'quantity' => 2, 'equipped' => false],
        ];
        $operations = [
            ['action' => 'remove', 'item_name' => 'Alte Fackel', 'quantity' => 1, 'equipped' => false],
            ['action' => 'add', 'item_name' => 'Neue Fackel', 'quantity' => 2, 'equipped' => false],
        ];

        $attributeNormalizer = $this->createMock(AttributeNormalizer::class);
        $attributeNormalizer->expects($this->once())
            ->method('normalizeForUpdate')
            ->with($payload, $character)
            ->willReturn($normalizedData);
        app()->instance(AttributeNormalizer::class, $attributeNormalizer);

        $normalizeCall = 0;
        $inventoryService = $this->createMock(CharacterInventoryService::class);
        $inventoryService->expects($this->exactly(2))
            ->method('normalize')
            ->willReturnCallback(function (mixed $inventory) use (&$normalizeCall, $normalizedData, $previousInventory, $nextInventory): array {
                $normalizeCall++;

                if ($normalizeCall === 1) {
                    $this->assertSame([
                        ['name' => 'Alte Fackel', 'quantity' => 1, 'equipped' => false],
                    ], $inventory);

                    return $previousInventory;
                }

                $this->assertSame($normalizedData['inventory'], $inventory);

                return $nextInventory;
            });
        $inventoryService->expects($this->once())
            ->method('diff')
            ->with($previousInventory, $nextInventory)
            ->willReturn($operations);
        $inventoryService->expects($this->once())
            ->method('log')
            ->with(
                $this->callback(static fn (Character $updatedCharacter): bool => $updatedCharacter->is($character)),
                $this->equalTo((int) $user->id),
                $this->equalTo('character_sheet_update'),
                $this->equalTo($operations),
                $this->isNull(),
                $this->callback(
                    static fn (array $context): bool => isset($context['character_id'])
                        && (int) $context['character_id'] === (int) $character->id
                ),
            );
        app()->instance(CharacterInventoryService::class, $inventoryService);

        app(UpdateCharacterAction::class)->execute(
            new UpdateCharacterInput(
                actor: $user,
                character: $character,
                payload: $payload,
                removeAvatar: false,
                avatar: null,
            )
        );

        $this->assertDatabaseHas('characters', [
            'id' => $character->id,
            'name' => 'Neuer Name',
        ]);
    }

    public function test_it_replaces_avatar_and_deletes_previous_avatar_file(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $character = Character::factory()->create([
            'user_id' => $user->id,
            'avatar_path' => 'character-avatars/alt-avatar.png',
            'inventory' => [],
        ]);
        Storage::disk('public')->put('character-avatars/alt-avatar.png', 'legacy');

        $avatar = UploadedFile::fake()->image('avatar.png', 160, 160);
        $payload = ['name' => 'Mit Avatar'];

        $attributeNormalizer = $this->createMock(AttributeNormalizer::class);
        $attributeNormalizer->expects($this->once())
            ->method('normalizeForUpdate')
            ->with($payload, $character)
            ->willReturn([
                'name' => 'Mit Avatar',
                'inventory' => [],
            ]);
        app()->instance(AttributeNormalizer::class, $attributeNormalizer);

        $inventoryService = $this->createMock(CharacterInventoryService::class);
        $inventoryService->expects($this->exactly(2))
            ->method('normalize')
            ->willReturn([]);
        $inventoryService->expects($this->once())
            ->method('diff')
            ->with([], [])
            ->willReturn([]);
        $inventoryService->expects($this->once())
            ->method('log')
            ->with(
                $this->callback(static fn (Character $updatedCharacter): bool => $updatedCharacter->is($character)),
                $this->equalTo((int) $user->id),
                $this->equalTo('character_sheet_update'),
                $this->equalTo([]),
                $this->isNull(),
                $this->callback(
                    static fn (array $context): bool => isset($context['character_id'])
                        && (int) $context['character_id'] === (int) $character->id
                ),
            );
        app()->instance(CharacterInventoryService::class, $inventoryService);

        app(UpdateCharacterAction::class)->execute(
            new UpdateCharacterInput(
                actor: $user,
                character: $character,
                payload: $payload,
                removeAvatar: false,
                avatar: $avatar,
            )
        );

        $character->refresh();

        $this->assertNotNull($character->avatar_path);
        $this->assertNotSame('character-avatars/alt-avatar.png', $character->avatar_path);
        Storage::disk('public')->assertMissing('character-avatars/alt-avatar.png');
        Storage::disk('public')->assertExists((string) $character->avatar_path);
    }
}
