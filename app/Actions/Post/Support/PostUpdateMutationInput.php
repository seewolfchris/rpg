<?php

declare(strict_types=1);

namespace App\Actions\Post\Support;

use Illuminate\Http\UploadedFile;

final readonly class PostUpdateMutationInput
{
    public function __construct(
        public string $postType,
        public string $postMode,
        public ?int $characterId,
        public string $contentFormat,
        public string $content,
        public string $icQuote,
        /** @var list<UploadedFile> */
        public array $immersiveImages,
        /** @var list<int> */
        public array $removeImmersiveMediaIds,
        public ?string $moderationStatus,
        public string $moderationNote,
    ) {}

    /**
     * @param  array{
     *   post_type: string,
     *   post_mode?: string,
     *   character_id?: mixed,
     *   content_format: string,
     *   content: string,
     *   ic_quote?: mixed,
     *   immersive_images?: mixed,
     *   remove_immersive_media_ids?: mixed,
     *   moderation_status?: mixed,
     *   moderation_note?: mixed
     * }  $data
     */
    public static function fromArray(array $data): self
    {
        $postType = (string) $data['post_type'];
        $postMode = $postType === 'ic'
            ? (string) ($data['post_mode'] ?? 'character')
            : 'character';

        $characterId = null;
        if ($postType === 'ic' && $postMode === 'character') {
            $rawCharacterId = $data['character_id'] ?? null;
            $characterId = $rawCharacterId !== null ? (int) $rawCharacterId : null;
        }

        $rawImmersiveImages = $data['immersive_images'] ?? [];
        $immersiveImages = $rawImmersiveImages instanceof UploadedFile
            ? [$rawImmersiveImages]
            : (is_array($rawImmersiveImages) ? $rawImmersiveImages : []);

        /** @var list<UploadedFile> $normalizedImmersiveImages */
        $normalizedImmersiveImages = [];
        foreach ($immersiveImages as $immersiveImage) {
            if (! $immersiveImage instanceof UploadedFile) {
                continue;
            }

            $normalizedImmersiveImages[] = $immersiveImage;
        }

        $rawRemoveImmersiveMediaIds = $data['remove_immersive_media_ids'] ?? [];
        $removeImmersiveMediaIds = is_array($rawRemoveImmersiveMediaIds)
            ? $rawRemoveImmersiveMediaIds
            : [];

        /** @var list<int> $normalizedRemoveImmersiveMediaIds */
        $normalizedRemoveImmersiveMediaIds = [];
        foreach ($removeImmersiveMediaIds as $removeMediaId) {
            $normalizedId = is_numeric($removeMediaId) ? (int) $removeMediaId : 0;

            if ($normalizedId <= 0) {
                continue;
            }

            $normalizedRemoveImmersiveMediaIds[] = $normalizedId;
        }
        $normalizedRemoveImmersiveMediaIds = array_values(array_unique($normalizedRemoveImmersiveMediaIds));

        return new self(
            postType: $postType,
            postMode: $postMode,
            characterId: $characterId,
            contentFormat: (string) $data['content_format'],
            content: (string) $data['content'],
            icQuote: (string) ($data['ic_quote'] ?? ''),
            immersiveImages: $normalizedImmersiveImages,
            removeImmersiveMediaIds: $normalizedRemoveImmersiveMediaIds,
            moderationStatus: isset($data['moderation_status']) ? (string) $data['moderation_status'] : null,
            moderationNote: (string) ($data['moderation_note'] ?? ''),
        );
    }
}
