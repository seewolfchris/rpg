<?php

declare(strict_types=1);

namespace App\Actions\Character;

use App\Exceptions\CharacterDeletionFailedException;
use App\Models\Character;
use App\Models\CharacterInventoryLog;
use App\Models\CharacterProgressionEvent;
use App\Models\DiceRoll;
use App\Models\Post;
use App\Models\PostMention;
use App\Models\PostRevision;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Throwable;

class DeleteCharacterAction
{
    public function __construct(
        private readonly DatabaseManager $db,
        private readonly FilesystemFactory $filesystem,
    ) {}

    /**
     * @throws ModelNotFoundException
     * @throws CharacterDeletionFailedException
     */
    public function execute(Character $character): void
    {
        $characterId = (int) $character->getKey();

        if ($characterId <= 0) {
            throw (new ModelNotFoundException())->setModel(Character::class, [$characterId]);
        }

        try {
            $this->db->transaction(function () use ($characterId): void {
                /** @var Character|null $lockedCharacter */
                $lockedCharacter = Character::query()
                    ->whereKey($characterId)
                    ->lockForUpdate()
                    ->first();

                if (! $lockedCharacter instanceof Character) {
                    throw (new ModelNotFoundException())->setModel(Character::class, [$characterId]);
                }

                PostMention::query()
                    ->where('mentioned_character_id', $characterId)
                    ->delete();

                CharacterInventoryLog::query()
                    ->where('character_id', $characterId)
                    ->delete();

                CharacterProgressionEvent::query()
                    ->where('character_id', $characterId)
                    ->delete();

                Post::query()
                    ->where('character_id', $characterId)
                    ->update(['character_id' => null]);

                PostRevision::query()
                    ->where('character_id', $characterId)
                    ->update(['character_id' => null]);

                DiceRoll::query()
                    ->where('character_id', $characterId)
                    ->update(['character_id' => null]);

                $avatarPath = trim((string) ($lockedCharacter->avatar_path ?? ''));
                if ($avatarPath !== '') {
                    $this->db->connection()->afterCommit(function () use ($avatarPath): void {
                        try {
                            $this->filesystem->disk('public')->delete($avatarPath);
                        } catch (Throwable $throwable) {
                            report($throwable);
                        }
                    });
                }

                $lockedCharacter->deleteOrFail();
            });
        } catch (ModelNotFoundException $exception) {
            throw $exception;
        } catch (Throwable $throwable) {
            throw CharacterDeletionFailedException::fromThrowable($throwable);
        }
    }
}
