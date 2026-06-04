<?php

namespace App\Http\Requests\Scene;

use App\Models\Campaign;
use App\Models\Scene;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class UpdateSceneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Campaign $campaign */
        $campaign = $this->route('campaign');
        /** @var Scene $scene */
        $scene = $this->route('scene');

        return [
            'title' => ['required', 'string', 'max:150'],
            'slug' => [
                'required',
                'string',
                'max:170',
                'alpha_dash',
                Rule::unique('scenes', 'slug')
                    ->where(fn ($query) => $query->where('campaign_id', $campaign->id))
                    ->ignore($scene->id),
            ],
            'previous_scene_id' => ['nullable', 'integer', 'exists:scenes,id'],
            'summary' => ['nullable', 'string', 'max:1200'],
            'description' => ['nullable', 'string', 'max:15000'],
            'header_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'mimetypes:image/jpeg,image/png,image/webp,image/avif', 'max:4096'],
            'remove_header_image' => ['sometimes', 'boolean'],
            'content_images' => ['nullable', 'array', 'max:4'],
            'content_images.*' => [
                'bail',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'mimetypes:image/jpeg,image/png,image/webp',
                'max:4096',
            ],
            'remove_content_media_ids' => ['nullable', 'array'],
            'remove_content_media_ids.*' => ['integer', 'distinct'],
            'status' => ['required', Rule::in(['open', 'closed', 'archived'])],
            'mood' => ['required', Rule::in($this->moodKeys())],
            'position' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'allow_ooc' => ['sometimes', 'boolean'],
            'opens_at' => ['nullable', 'date'],
            'closes_at' => ['nullable', 'date', 'after_or_equal:opens_at'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $slugInput = $this->input('slug');
        $titleInput = $this->input('title');

        $this->merge([
            'slug' => Str::slug((string) ($slugInput ?: $titleInput)),
            'mood' => (string) $this->input('mood', (string) config('scenes.default_mood', 'neutral')),
            'allow_ooc' => $this->boolean('allow_ooc'),
            'remove_header_image' => $this->boolean('remove_header_image'),
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var Campaign $campaign */
            $campaign = $this->route('campaign');
            /** @var Scene $scene */
            $scene = $this->route('scene');
            $previousSceneId = (int) ($this->input('previous_scene_id') ?? 0);
            $newContentImages = $this->uploadedContentImages();
            $newContentImageCount = count($newContentImages);
            $removeContentMediaIds = $this->removeContentMediaIds();

            $scene->loadMissing('media');
            $currentContentMedia = $scene->media
                ->where('collection_name', Scene::CONTENT_IMAGES_COLLECTION)
                ->values();
            $currentContentMediaIds = $currentContentMedia
                ->pluck('id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->filter(static fn (int $id): bool => $id > 0)
                ->values()
                ->all();

            if ($removeContentMediaIds !== []) {
                $mediaItems = Media::query()
                    ->whereIn('id', $removeContentMediaIds)
                    ->get(['id', 'model_type', 'model_id', 'collection_name']);

                if ($mediaItems->count() !== count($removeContentMediaIds)) {
                    $validator->errors()->add(
                        'remove_content_media_ids',
                        'Es können nur bestehende Bilder dieser Szene entfernt werden.'
                    );
                } else {
                    foreach ($mediaItems as $mediaItem) {
                        if (
                            $mediaItem->model_type !== Scene::class
                            || (int) $mediaItem->model_id !== (int) $scene->id
                            || (string) $mediaItem->collection_name !== Scene::CONTENT_IMAGES_COLLECTION
                        ) {
                            $validator->errors()->add(
                                'remove_content_media_ids',
                                'Es können nur bestehende Bilder dieser Szene entfernt werden.'
                            );

                            break;
                        }
                    }
                }
            }

            $validRemovalCount = count(array_intersect($removeContentMediaIds, $currentContentMediaIds));
            $projectedContentMediaCount = $currentContentMedia->count() - $validRemovalCount + $newContentImageCount;

            if ($projectedContentMediaCount > 4) {
                $validator->errors()->add(
                    'content_images',
                    'Eine Szene darf maximal 4 Bilder in der Szenenbeschreibung enthalten.'
                );
            }

            if ($previousSceneId <= 0) {
                return;
            }

            if ($previousSceneId === (int) $scene->id) {
                $validator->errors()->add('previous_scene_id', 'Eine Szene kann nicht auf sich selbst verweisen.');

                return;
            }

            $isSameCampaign = $campaign->scenes()
                ->whereKey($previousSceneId)
                ->exists();

            if (! $isSameCampaign) {
                $validator->errors()->add('previous_scene_id', 'Die Vorgängerszene muss zur gleichen Kampagne gehören.');
            }
        });
    }

    /**
     * @return list<string>
     */
    private function moodKeys(): array
    {
        /** @var list<string> $keys */
        $keys = array_keys((array) config('scenes.moods', []));

        return $keys;
    }

    /**
     * @return list<UploadedFile>
     */
    public function uploadedContentImages(): array
    {
        $rawFiles = $this->file('content_images', []);
        $files = $rawFiles instanceof UploadedFile
            ? [$rawFiles]
            : (is_array($rawFiles) ? $rawFiles : []);

        $uploadedFiles = [];

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $uploadedFiles[] = $file;
        }

        return $uploadedFiles;
    }

    /**
     * @return list<int>
     */
    public function removeContentMediaIds(): array
    {
        $rawIds = $this->input('remove_content_media_ids', []);
        $ids = is_array($rawIds) ? $rawIds : [];
        $normalizedIds = [];

        foreach ($ids as $id) {
            $normalizedId = is_numeric($id) ? (int) $id : 0;

            if ($normalizedId <= 0) {
                continue;
            }

            $normalizedIds[] = $normalizedId;
        }

        return array_values(array_unique($normalizedIds));
    }
}
