<?php

declare(strict_types=1);

namespace App\Actions\Post\Support;

final readonly class PostUpdateMutationInput
{
    public function __construct(
        public string $postType,
        public string $postMode,
        public ?int $characterId,
        public string $contentFormat,
        public string $content,
        public string $icQuote,
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

        return new self(
            postType: $postType,
            postMode: $postMode,
            characterId: $characterId,
            contentFormat: (string) $data['content_format'],
            content: (string) $data['content'],
            icQuote: (string) ($data['ic_quote'] ?? ''),
            moderationStatus: isset($data['moderation_status']) ? (string) $data['moderation_status'] : null,
            moderationNote: (string) ($data['moderation_note'] ?? ''),
        );
    }
}
