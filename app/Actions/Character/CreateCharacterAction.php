<?php

declare(strict_types=1);

namespace App\Actions\Character;

use App\Exceptions\CharacterCreationFailedException;
use App\Http\Requests\Character\StoreCharacterRequest;
use App\Models\Character;
use App\Services\Character\AttributeNormalizer;
use App\Services\Character\AvatarService;
use App\Support\CharacterInventoryService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Throwable;

class CreateCharacterAction
{
    public function __construct(
        private readonly AttributeNormalizer $attributeNormalizer,
        private readonly AvatarService $avatarService,
        private readonly CharacterInventoryService $inventoryService,
        private readonly DatabaseManager $db,
    ) {}

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     * @throws ModelNotFoundException
     * @throws CharacterCreationFailedException
     */
    public function execute(StoreCharacterRequest $request): Character
    {
        $user = $request->user();
        if ($user === null) {
            throw new AuthorizationException('Missing authenticated user.');
        }

        $stagedAvatar = null;

        try {
            $stagedAvatar = $this->avatarService->stageFromRequest($request);

            $character = $this->db->transaction(function () use ($request, $user, $stagedAvatar): Character {
                $data = $this->attributeNormalizer->normalizeForCreate($request);
                $data['avatar_path'] = null;

                $character = new Character($data);
                $character->user_id = (int) $user->id;
                $character->saveOrFail();

                $normalizedInventory = $this->inventoryService->normalize($character->inventory ?? []);
                $operations = $this->inventoryService->diff([], $normalizedInventory);
                $this->inventoryService->log(
                    character: $character,
                    actorUserId: (int) $user->id,
                    source: 'character_sheet_create',
                    operations: $operations,
                    context: ['character_id' => $character->id],
                );

                if ($stagedAvatar !== null) {
                    $this->db->connection()->afterCommit(
                        fn () => $this->avatarService->finalizeForCharacter($stagedAvatar, $character)
                    );
                }

                return $character;
            });

            return $character->fresh() ?? $character;
        } catch (AuthorizationException | ValidationException | ModelNotFoundException $throwable) {
            $this->avatarService->discardStageIfPresent($stagedAvatar);

            throw $throwable;
        } catch (CharacterCreationFailedException $throwable) {
            $this->avatarService->discardStageIfPresent($stagedAvatar);

            throw $throwable;
        } catch (Throwable $throwable) {
            $this->avatarService->discardStageIfPresent($stagedAvatar);

            throw CharacterCreationFailedException::fromThrowable($throwable);
        }
    }
}
