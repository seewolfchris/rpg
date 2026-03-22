<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Character;

use App\Actions\Character\CreateCharacterAction;
use App\Http\Requests\Character\StoreCharacterRequest;
use App\Models\Character;
use App\Models\User;
use App\Models\World;
use App\Services\Character\AttributeNormalizer;
use App\Services\Character\AvatarService;
use App\Support\CharacterInventoryService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CreateCharacterActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_character_and_logs_inventory_with_mocks(): void
    {
        $user = User::factory()->create();
        $request = new StoreCharacterRequestFake(fakeUser: $user);

        $normalizedData = $this->normalizedCharacterData();
        $normalizedInventory = [[
            'name' => 'Torch',
            'quantity' => 2,
            'equipped' => false,
        ]];
        $operations = [[
            'action' => 'add',
            'item_name' => 'Torch',
            'quantity' => 2,
            'equipped' => false,
        ]];

        $attributeNormalizer = $this->createMock(AttributeNormalizer::class);
        $attributeNormalizer->expects($this->once())
            ->method('normalizeForCreate')
            ->with($request)
            ->willReturn($normalizedData);
        app()->instance(AttributeNormalizer::class, $attributeNormalizer);

        $avatarService = $this->createMock(AvatarService::class);
        $avatarService->expects($this->once())
            ->method('stageFromRequest')
            ->with($request)
            ->willReturn(null);
        $avatarService->expects($this->never())
            ->method('finalizeForCharacter');
        $avatarService->expects($this->never())
            ->method('discardStageIfPresent');
        app()->instance(AvatarService::class, $avatarService);

        $inventoryService = $this->createMock(CharacterInventoryService::class);
        $inventoryService->expects($this->once())
            ->method('normalize')
            ->with($normalizedData['inventory'])
            ->willReturn($normalizedInventory);
        $inventoryService->expects($this->once())
            ->method('diff')
            ->with([], $normalizedInventory)
            ->willReturn($operations);
        $inventoryService->expects($this->once())
            ->method('log')
            ->with(
                $this->callback(static fn (Character $character): bool => $character->exists),
                $this->equalTo((int) $user->id),
                $this->equalTo('character_sheet_create'),
                $this->equalTo($operations),
                $this->isNull(),
                $this->callback(
                    static fn (array $context): bool => isset($context['character_id'])
                        && is_int($context['character_id'])
                        && $context['character_id'] > 0
                ),
            );
        app()->instance(CharacterInventoryService::class, $inventoryService);

        $character = app(CreateCharacterAction::class)->execute($request);

        $this->assertInstanceOf(Character::class, $character);
        $this->assertDatabaseHas('characters', [
            'id' => $character->id,
            'user_id' => $user->id,
            'name' => 'Unit Hero',
        ]);
    }

    public function test_it_throws_authorization_exception_when_request_has_no_user(): void
    {
        $request = new StoreCharacterRequestFake(fakeUser: null);

        $attributeNormalizer = $this->createMock(AttributeNormalizer::class);
        $attributeNormalizer->expects($this->never())
            ->method('normalizeForCreate');
        app()->instance(AttributeNormalizer::class, $attributeNormalizer);

        $avatarService = $this->createMock(AvatarService::class);
        $avatarService->expects($this->never())
            ->method('stageFromRequest');
        app()->instance(AvatarService::class, $avatarService);

        $inventoryService = $this->createMock(CharacterInventoryService::class);
        $inventoryService->expects($this->never())
            ->method('normalize');
        $inventoryService->expects($this->never())
            ->method('diff');
        $inventoryService->expects($this->never())
            ->method('log');
        app()->instance(CharacterInventoryService::class, $inventoryService);

        $this->expectException(AuthorizationException::class);

        app(CreateCharacterAction::class)->execute($request);
    }

    public function test_it_persists_character_and_inventory_log_in_short_integration_case(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $avatar = UploadedFile::fake()->image('avatar.png', 220, 220);
        $request = new StoreCharacterRequestFake(
            fakeUser: $user,
            validated: $this->validatedPayload(),
            derivedPools: [
                'le_max' => 42,
                'le_current' => 42,
                'ae_max' => 0,
                'ae_current' => 0,
            ],
            avatar: $avatar,
        );

        $character = app(CreateCharacterAction::class)->execute($request);

        $this->assertDatabaseHas('characters', [
            'id' => $character->id,
            'user_id' => $user->id,
            'name' => 'Aldric',
        ]);
        $this->assertNotNull($character->avatar_path);
        Storage::disk('public')->assertExists((string) $character->avatar_path);

        $this->assertDatabaseHas('character_inventory_logs', [
            'character_id' => $character->id,
            'actor_user_id' => $user->id,
            'source' => 'character_sheet_create',
            'action' => 'add',
            'item_name' => 'Seil 10m lang',
            'quantity' => 1,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizedCharacterData(): array
    {
        return [
            'world_id' => World::resolveDefaultId(),
            'name' => 'Unit Hero',
            'bio' => str_repeat('History line. ', 4),
            'status' => 'active',
            'origin' => 'native_vhaltor',
            'species' => 'mensch',
            'calling' => 'abenteurer',
            'advantages' => ['Diszipliniert'],
            'disadvantages' => ['Misstrauisch'],
            'inventory' => [[
                'name' => 'Torch',
                'quantity' => 2,
                'equipped' => false,
            ]],
            'weapons' => [],
            'armors' => [],
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
            'le_max' => 40,
            'le_current' => 40,
            'ae_max' => 0,
            'ae_current' => 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedPayload(): array
    {
        return [
            'world_id' => World::resolveDefaultId(),
            'name' => 'Aldric',
            'epithet' => 'der Graupriester',
            'bio' => str_repeat('Dunkle Geschichte. ', 4),
            'status' => 'active',
            'origin' => 'native_vhaltor',
            'species' => 'mensch',
            'calling' => 'abenteurer',
            'concept' => 'Ich jage Wahrheiten durch Asche und Nebel.',
            'gm_secret' => 'Ich schulde der Schattenbank von Nerez einen Eid.',
            'world_connection' => 'Meine Schwester dient den Glutrichtern als Schreiberin.',
            'advantages' => ['Blutpforten-Sinn'],
            'disadvantages' => ['Aschesucht'],
            'inventory' => [[
                'name' => 'Seil 10m lang',
                'quantity' => 1,
                'equipped' => false,
            ], [
                'name' => 'Feuerstein',
                'quantity' => 3,
                'equipped' => false,
            ]],
            'weapons' => [[
                'name' => 'Kurzschwert',
                'attack' => 48,
                'parry' => 41,
                'damage' => 12,
            ]],
            'armors' => [[
                'name' => 'Lederruestung',
                'protection' => 5,
                'equipped' => true,
            ]],
            'gm_note' => 'Vorteil/Nachteil fuer Kampagne freigegeben.',
            'mu' => 40,
            'kl' => 45,
            'in' => 40,
            'ch' => 35,
            'ff' => 40,
            'ge' => 40,
            'ko' => 45,
            'kk' => 40,
            'mu_note' => 'Haelt auch in Finsternis den Blick gerade.',
            'kl_note' => 'Liest Archive schneller als andere Gesichter.',
            'in_note' => 'Vertraut dem Druecken der Stille.',
            'ch_note' => 'Wirkt warm, bleibt aber unnahbar.',
            'ff_note' => 'Feine Hand bei Siegeln und Schlossnadeln.',
            'ge_note' => 'Leichtfussig trotz schwerem Mantel.',
            'ko_note' => 'Zaeh wie alter Lederpanzer.',
            'kk_note' => 'Schultert Lasten ohne Klage.',
            'avatar' => 'ignored-in-normalizer',
        ];
    }
}

final class StoreCharacterRequestFake extends StoreCharacterRequest
{
    /**
     * @param  array<string, mixed>  $validated
     * @param  array{le_max: int, le_current: int, ae_max: int, ae_current: int}  $derivedPools
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
