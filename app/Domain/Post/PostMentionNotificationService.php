<?php

namespace App\Domain\Post;

use App\Domain\Campaign\CampaignParticipantResolver;
use App\Models\Campaign;
use App\Models\Character;
use App\Models\Post;
use App\Models\PostMention;
use App\Models\Scene;
use App\Models\User;
use App\Notifications\CharacterMentionNotification;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use RuntimeException;

class PostMentionNotificationService
{
    public function __construct(
        private readonly CampaignParticipantResolver $campaignParticipantResolver,
    ) {}

    /**
     * @return int Anzahl benachrichtigter Nutzer
     */
    public function notifyMentions(Post $post, User $author): int
    {
        if ((string) $post->moderation_status !== 'approved'
            || ! (bool) config('features.wave4.mentions', false)) {
            return 0;
        }

        $content = (string) ($post->content ?? '');
        preg_match_all('/@([A-Za-z0-9][A-Za-z0-9_-]{1,39})/', $content, $matches);

        /** @var list<string> $rawMentionTokens */
        $rawMentionTokens = array_values(array_unique($matches[1]));

        if ($rawMentionTokens === []) {
            return 0;
        }

        $post->loadMissing(['scene.campaign']);
        /** @var Scene $scene */
        $scene = $post->scene;
        /** @var Campaign $campaign */
        $campaign = $scene->campaign;
        $worldId = (int) $campaign->world_id;

        $participantUserIds = $this->campaignParticipantResolver
            ->participantUserIds($campaign);

        $characters = Character::query()
            ->where('world_id', $worldId)
            ->whereIn('user_id', $participantUserIds)
            ->get(['id', 'name', 'user_id']);

        $lookup = [];
        foreach ($characters as $character) {
            $normalizedKey = $this->normalizeMentionToken($character->name);

            if ($normalizedKey === '' || array_key_exists($normalizedKey, $lookup)) {
                continue;
            }

            $lookup[$normalizedKey] = $character;
        }

        /** @var array<int, array<int, string>> $mentionsByUser */
        $mentionsByUser = [];
        foreach ($rawMentionTokens as $token) {
            $normalizedToken = $this->normalizeMentionToken($token);
            $matchedCharacter = $lookup[$normalizedToken] ?? null;

            if (! $matchedCharacter instanceof Character) {
                continue;
            }

            if ((int) $matchedCharacter->user_id === (int) $author->id) {
                continue;
            }

            $userId = (int) $matchedCharacter->user_id;
            if (! $this->campaignParticipantResolver->isParticipantUserId($campaign, $userId, $participantUserIds)) {
                continue;
            }

            $mentionsByUser[$userId] ??= [];
            $mentionsByUser[$userId][(int) $matchedCharacter->id] = (string) $matchedCharacter->name;
        }

        if ($mentionsByUser === []) {
            return 0;
        }

        $recipients = User::query()
            ->whereIn('id', array_keys($mentionsByUser))
            ->get();

        $notifiedUsers = 0;

        foreach ($recipients as $recipient) {
            $mentionedCharacters = $mentionsByUser[(int) $recipient->id] ?? [];
            $newMentionNames = [];

            if ($mentionedCharacters === []) {
                continue;
            }

            foreach ($mentionedCharacters as $characterId => $characterName) {
                $mentionRecord = $this->findOrCreateMentionRecord(
                    post: $post,
                    recipient: $recipient,
                    characterId: (int) $characterId,
                    characterName: $characterName,
                );

                if ($mentionRecord->wasRecentlyCreated) {
                    $newMentionNames[] = $characterName;

                    continue;
                }

                if ((string) $mentionRecord->mentioned_character_name !== $characterName) {
                    $mentionRecord->mentioned_character_name = $characterName;
                    $mentionRecord->save();
                }
            }

            $newMentionNames = array_values(array_unique($newMentionNames));

            if ($newMentionNames === []) {
                continue;
            }

            Notification::send($recipient, new CharacterMentionNotification(
                post: $post,
                author: $author,
                mentionedCharacterNames: $newMentionNames,
            ));

            $notifiedUsers += 1;
        }

        return $notifiedUsers;
    }

    private function findOrCreateMentionRecord(
        Post $post,
        User $recipient,
        int $characterId,
        string $characterName,
    ): PostMention {
        try {
            return PostMention::query()->firstOrCreate([
                'post_id' => (int) $post->id,
                'mentioned_character_id' => $characterId,
            ], [
                'mentioned_user_id' => (int) $recipient->id,
                'mentioned_character_name' => $characterName,
            ]);
        } catch (QueryException $exception) {
            if (! $this->isDuplicateMentionKey($exception)) {
                throw $exception;
            }
        }

        $existingMention = PostMention::query()
            ->where('post_id', (int) $post->id)
            ->where('mentioned_character_id', $characterId)
            ->first();

        if ($existingMention instanceof PostMention) {
            return $existingMention;
        }

        throw new RuntimeException('Post mention duplicate key recovery failed.');
    }

    private function isDuplicateMentionKey(QueryException $exception): bool
    {
        $errorInfo = $exception->errorInfo;
        $driverCode = is_array($errorInfo) && isset($errorInfo[1])
            ? (int) $errorInfo[1]
            : 0;
        $message = strtolower($exception->getMessage());

        if ($driverCode === 1062) {
            return true;
        }

        if (str_contains($message, 'duplicate entry')) {
            return true;
        }

        return str_contains($message, 'unique constraint failed');
    }

    private function normalizeMentionToken(string $value): string
    {
        return (string) Str::of($value)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]/', '')
            ->trim();
    }
}
