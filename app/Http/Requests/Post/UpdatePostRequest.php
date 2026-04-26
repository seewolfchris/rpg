<?php

namespace App\Http\Requests\Post;

use App\Domain\Campaign\CampaignParticipantResolver;
use App\Models\Campaign;
use App\Models\Character;
use App\Models\Post;
use App\Models\Scene;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdatePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $post = $this->route('post');

        return $user !== null
            && $post instanceof Post
            && $user->can('update', $post);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'post_type' => ['required', Rule::in(['ic', 'ooc'])],
            'post_mode' => ['nullable', Rule::in(['character', 'gm'])],
            'character_id' => ['nullable', 'integer'],
            'content_format' => ['required', Rule::in(['markdown', 'bbcode', 'plain'])],
            'content' => ['required', 'string', 'min:5', 'max:10000'],
            'ic_quote' => ['nullable', 'string', 'max:180'],
            'immersive_images' => ['nullable', 'array', 'max:4'],
            'immersive_images.*' => [
                'bail',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'mimetypes:image/jpeg,image/png,image/webp',
                'max:4096',
            ],
            'remove_immersive_media_ids' => ['nullable', 'array'],
            'remove_immersive_media_ids.*' => ['integer', 'distinct'],
            'moderation_status' => ['nullable', Rule::in(['pending', 'approved', 'rejected'])],
            'moderation_note' => ['nullable', 'string', 'max:500'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $quote = trim((string) $this->input('ic_quote', ''));
        $postType = (string) $this->input('post_type', 'ic');
        $postMode = strtolower(trim((string) $this->input('post_mode', '')));

        if ($postMode === '') {
            $postMode = 'character';
        }

        if ($postType !== 'ic') {
            $postMode = 'character';
        }

        $normalized = [
            'post_mode' => $postMode,
            'ic_quote' => $quote !== '' ? $quote : null,
            'moderation_note' => trim((string) $this->input('moderation_note', '')),
        ];

        if ($postType !== 'ic') {
            $normalized['character_id'] = null;
        }

        $this->merge($normalized);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var Post|null $post */
            $post = $this->route('post');

            if (! $post) {
                $validator->errors()->add('post', 'Beitrag konnte nicht gefunden werden.');

                return;
            }

            /** @var Scene $scene */
            $scene = $post->scene;
            /** @var Campaign $campaign */
            $campaign = $scene->campaign;
            $postType = (string) $this->input('post_type');
            $postMode = $postType === 'ic'
                ? (string) $this->input('post_mode', 'character')
                : 'character';
            $characterId = $this->filled('character_id')
                ? (int) $this->input('character_id')
                : null;
            $user = $this->user();
            $canModerate = $user !== null && $user->can('moderate', $post);
            $isFinalGmNarration = $postType === 'ic' && $postMode === 'gm';
            $newImmersiveImages = $this->immersiveImagesFromInput();
            $newImmersiveImageCount = count($newImmersiveImages);

            $post->loadMissing('media');
            $currentImmersiveMedia = $post->media
                ->where('collection_name', Post::IMMERSIVE_IMAGES_COLLECTION)
                ->values();
            $currentImmersiveMediaCount = $currentImmersiveMedia->count();
            $currentImmersiveMediaIds = $currentImmersiveMedia
                ->pluck('id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->filter(static fn (int $id): bool => $id > 0)
                ->values()
                ->all();
            $removeImmersiveMediaIds = $this->normalizedRemoveImmersiveMediaIds();

            $invalidRemoveIds = array_values(array_diff($removeImmersiveMediaIds, $currentImmersiveMediaIds));
            if ($invalidRemoveIds !== []) {
                $validator->errors()->add(
                    'remove_immersive_media_ids',
                    'Es können nur bestehende immersive Bilder dieses Beitrags entfernt werden.'
                );
            }

            $validRemovalCount = count(array_intersect($removeImmersiveMediaIds, $currentImmersiveMediaIds));

            if ($postType === 'ooc' && ! $scene->allow_ooc && ! $canModerate) {
                $validator->errors()->add('post_type', 'OOC-Beiträge sind in dieser Szene deaktiviert.');
            }

            if ($postType === 'ic' && $postMode === 'character' && ! $characterId) {
                $validator->errors()->add('character_id', 'Für IC-Beiträge als Charakter ist ein Charakter erforderlich.');
            }

            if ($postType === 'ic' && $postMode === 'gm') {
                if (! $canModerate) {
                    $validator->errors()->add('post_mode', 'Nur GM oder Co-GM dürfen als Spielleitung posten.');
                }

                if ($characterId !== null) {
                    $validator->errors()->add('character_id', 'Für Spielleitungsbeiträge darf kein Charakter gesetzt sein.');
                }
            }

            if ($postType !== 'ic' && trim((string) ($this->input('ic_quote') ?? '')) !== '') {
                $validator->errors()->add('ic_quote', 'Ein IC-Zitat ist nur für IC-Beiträge erlaubt.');
            }

            if ($newImmersiveImageCount > 0 && (! $isFinalGmNarration || ! $canModerate)) {
                $validator->errors()->add(
                    'immersive_images',
                    'Immersive Bilder sind nur für IC-Beiträge im Spielleitungsmodus erlaubt.'
                );
            }

            if (! $isFinalGmNarration && $currentImmersiveMediaCount > 0) {
                if ($newImmersiveImageCount > 0) {
                    $validator->errors()->add(
                        'immersive_images',
                        'Beim Wechsel weg vom Spielleitungsmodus dürfen keine neuen immersiven Bilder hochgeladen werden.'
                    );
                }

                if ($validRemovalCount !== $currentImmersiveMediaCount) {
                    $validator->errors()->add(
                        'remove_immersive_media_ids',
                        'Beim Wechsel weg vom Spielleitungsmodus müssen alle bestehenden immersiven Bilder explizit entfernt werden.'
                    );
                }
            }

            if ($isFinalGmNarration) {
                $projectedImmersiveMediaCount = $currentImmersiveMediaCount - $validRemovalCount + $newImmersiveImageCount;

                if ($projectedImmersiveMediaCount > 4) {
                    $validator->errors()->add(
                        'immersive_images',
                        'Ein Spielleitungsbeitrag darf maximal 4 immersive Bilder enthalten.'
                    );
                }
            }

            if ($postType === 'ic' && $postMode === 'character' && $characterId) {
                $campaignParticipantUserIds = $this->campaignParticipantResolver()
                    ->participantUserIds($campaign);

                /** @var Character|null $character */
                $character = Character::query()
                    ->select(['id', 'user_id', 'world_id'])
                    ->find($characterId);

                if (! $character instanceof Character) {
                    $validator->errors()->add('character_id', 'Charakter konnte nicht gefunden werden.');
                } elseif ((int) $character->world_id !== (int) $campaign->world_id) {
                    $validator->errors()->add('character_id', 'Der gewählte Charakter gehört nicht zur Welt dieser Kampagne.');
                } elseif ((int) $character->user_id <= 0) {
                    $validator->errors()->add('character_id', 'Der gewählte Charakter muss zu einem aktiven Kampagnen-Teilnehmer gehören.');
                } elseif ((int) $character->user_id !== (int) $post->user_id) {
                    $validator->errors()->add('character_id', 'Der gewählte Charakter muss dem ursprünglichen Autor dieses Beitrags gehören.');
                } elseif (! $campaignParticipantUserIds->contains((int) $character->user_id)) {
                    $validator->errors()->add('character_id', 'Der gewählte Charakter muss zu einem aktiven Kampagnen-Teilnehmer gehören.');
                }
            }
        });
    }

    private function campaignParticipantResolver(): CampaignParticipantResolver
    {
        /** @var CampaignParticipantResolver $resolver */
        $resolver = app(CampaignParticipantResolver::class);

        return $resolver;
    }

    /**
     * @return list<UploadedFile>
     */
    private function immersiveImagesFromInput(): array
    {
        $rawFiles = $this->file('immersive_images', []);
        $files = $rawFiles instanceof UploadedFile
            ? [$rawFiles]
            : (is_array($rawFiles) ? $rawFiles : []);

        $immersiveImages = [];

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $immersiveImages[] = $file;
        }

        return $immersiveImages;
    }

    /**
     * @return list<int>
     */
    private function normalizedRemoveImmersiveMediaIds(): array
    {
        $rawIds = $this->input('remove_immersive_media_ids', []);
        $removeIds = is_array($rawIds) ? $rawIds : [];

        $normalizedIds = [];

        foreach ($removeIds as $removeId) {
            $id = is_numeric($removeId) ? (int) $removeId : 0;

            if ($id <= 0) {
                continue;
            }

            $normalizedIds[] = $id;
        }

        $normalizedIds = array_values(array_unique($normalizedIds));

        return $normalizedIds;
    }
}
