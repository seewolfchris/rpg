<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Character;

use App\Actions\Character\UpdateCharacterAction;
use App\Http\Requests\Character\UpdateCharacterRequest;
use App\Models\Character;
use App\Models\User;
use App\Services\Character\AttributeNormalizer;
use App\Support\CharacterInventoryService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
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

        $request = new UpdateCharacterRequestFake(
            fakeUser: $user,
            booleanValues: ['remove_avatar' => false],
        );

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
            ->with($request, $character)
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

        app(UpdateCharacterAction::class)->execute($request, $character);

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
        $request = new UpdateCharacterRequestFake(
            fakeUser: $user,
            booleanValues: ['remove_avatar' => false],
            avatar: $avatar,
        );

        $attributeNormalizer = $this->createMock(AttributeNormalizer::class);
        $attributeNormalizer->expects($this->once())
            ->method('normalizeForUpdate')
            ->with($request, $character)
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

        app(UpdateCharacterAction::class)->execute($request, $character);

        $character->refresh();

        $this->assertNotNull($character->avatar_path);
        $this->assertNotSame('character-avatars/alt-avatar.png', $character->avatar_path);
        Storage::disk('public')->assertMissing('character-avatars/alt-avatar.png');
        Storage::disk('public')->assertExists((string) $character->avatar_path);
    }

    public function test_it_throws_authorization_exception_when_request_has_no_user(): void
    {
        $character = Character::factory()->create();
        $request = new UpdateCharacterRequestFake(fakeUser: null);

        $attributeNormalizer = $this->createMock(AttributeNormalizer::class);
        $attributeNormalizer->expects($this->never())
            ->method('normalizeForUpdate');
        app()->instance(AttributeNormalizer::class, $attributeNormalizer);

        $inventoryService = $this->createMock(CharacterInventoryService::class);
        $inventoryService->expects($this->never())
            ->method('normalize');
        $inventoryService->expects($this->never())
            ->method('diff');
        $inventoryService->expects($this->never())
            ->method('log');
        app()->instance(CharacterInventoryService::class, $inventoryService);

        $this->expectException(AuthorizationException::class);

        app(UpdateCharacterAction::class)->execute($request, $character);
    }
}

final class UpdateCharacterRequestFake extends UpdateCharacterRequest
{
    /**
     * @param  array<string, mixed>  $validated
     * @param  array{le_max: int, le_current: int, ae_max: int, ae_current: int}  $derivedPools
     * @param  array<string, bool>  $booleanValues
     */
    public function __construct(
        private readonly ?Authenticatable $fakeUser = null,
        private readonly array $validated = [],
        private readonly array $derivedPools = [
            'le_max' => 0,
            'le_current' => 0,
            'ae_max' => 0,
            'ae_current' => 0,
        ],
        private readonly array $booleanValues = [],
        private readonly ?UploadedFile $avatar = null,
    ) {}

    public function user($guard = null): ?Authenticatable
    {
        return $this->fakeUser;
    }

    public function validated($key = null, $default = null): mixed
    {
        if ($key === null) {
            return $this->validated;
        }

        return Arr::get($this->validated, (string) $key, $default);
    }

    /**
     * @return array{le_max: int, le_current: int, ae_max: int, ae_current: int}
     */
    public function derivedPools(): array
    {
        return $this->derivedPools;
    }

    public function boolean($key = null, $default = false): bool
    {
        if (! is_string($key)) {
            return (bool) $default;
        }

        return (bool) Arr::get($this->booleanValues, $key, $default);
    }

    public function hasFile($key): bool
    {
        return (string) $key === 'avatar' && $this->avatar instanceof UploadedFile;
    }

    public function file($key = null, $default = null): mixed
    {
        if ((string) $key === 'avatar' && $this->avatar instanceof UploadedFile) {
            return $this->avatar;
        }

        return $default;
    }
}
