<?php

namespace App\Models;

use App\Support\InlineImageSlotResolver;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Scene extends Model implements HasMedia
{
    /** @use HasFactory<\Database\Factories\SceneFactory> */
    use HasFactory;

    use InteractsWithMedia;

    public const CONTENT_IMAGES_COLLECTION = 'scene_content_images';

    /**
     * @var array<string, string>
     */
    private const STATUS_LABELS = [
        'open' => 'Offen',
        'closed' => 'Geschlossen',
        'archived' => 'Archiviert',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'campaign_id',
        'created_by',
        'title',
        'slug',
        'previous_scene_id',
        'summary',
        'description',
        'header_image_path',
        'status',
        'mood',
        'position',
        'allow_ooc',
        'opens_at',
        'closes_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'previous_scene_id' => 'integer',
            'allow_ooc' => 'boolean',
            'position' => 'integer',
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Campaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<Post, $this>
     */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    /**
     * @return HasMany<CampaignGmContactThread, $this>
     */
    public function gmContactThreads(): HasMany
    {
        return $this->hasMany(CampaignGmContactThread::class, 'scene_id');
    }

    /**
     * @return HasMany<Handout, $this>
     */
    public function handouts(): HasMany
    {
        return $this->hasMany(Handout::class, 'scene_id');
    }

    /**
     * @return HasMany<StoryLogEntry, $this>
     */
    public function storyLogEntries(): HasMany
    {
        return $this->hasMany(StoryLogEntry::class, 'scene_id');
    }

    /**
     * @return HasMany<CombatPhase, $this>
     */
    public function combatPhases(): HasMany
    {
        return $this->hasMany(CombatPhase::class, 'scene_id');
    }

    /**
     * @return HasMany<SceneConflictActor, $this>
     */
    public function sceneConflictActors(): HasMany
    {
        return $this->hasMany(SceneConflictActor::class, 'scene_id');
    }

    /**
     * @return HasMany<PlayerNote, $this>
     */
    public function playerNotes(): HasMany
    {
        return $this->hasMany(PlayerNote::class, 'scene_id');
    }

    /**
     * @return BelongsTo<Scene, $this>
     */
    public function previousScene(): BelongsTo
    {
        return $this->belongsTo(Scene::class, 'previous_scene_id');
    }

    /**
     * @return HasMany<DiceRoll, $this>
     */
    public function diceRolls(): HasMany
    {
        return $this->hasMany(DiceRoll::class);
    }

    /**
     * @return HasMany<SceneSubscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(SceneSubscription::class);
    }

    /**
     * @return HasMany<SceneBookmark, $this>
     */
    public function bookmarks(): HasMany
    {
        return $this->hasMany(SceneBookmark::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function subscribers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'scene_subscriptions')
            ->withPivot(['is_muted', 'last_read_post_id', 'last_read_at'])
            ->withTimestamps();
    }

    /**
     * @return Collection<int, \Spatie\MediaLibrary\MediaCollections\Models\Media>
     */
    public function contentImagesForDisplay(): Collection
    {
        $media = $this->relationLoaded('media')
            ? $this->media->where('collection_name', self::CONTENT_IMAGES_COLLECTION)
            : $this->getMedia(self::CONTENT_IMAGES_COLLECTION);

        $slotResolver = app(InlineImageSlotResolver::class);
        $resolution = $slotResolver->resolve($media);
        $assignedMedia = collect($resolution->mediaBySlot())
            ->sortKeys()
            ->values();
        $assignedMediaIds = $assignedMedia
            ->map(static fn ($mediaItem): int => (int) $mediaItem->id)
            ->all();

        return $assignedMedia
            ->concat(
                $resolution->orderedMedia()
                    ->reject(static fn ($mediaItem): bool => in_array((int) $mediaItem->id, $assignedMediaIds, true))
                    ->values()
            )
            ->values();
    }

    public function statusLabel(): string
    {
        return self::statusLabelFor((string) $this->status);
    }

    public static function statusLabelFor(string $status): string
    {
        return self::STATUS_LABELS[$status] ?? $status;
    }

    public function registerMediaCollections(): void
    {
        $this
            ->addMediaCollection(self::CONTENT_IMAGES_COLLECTION)
            ->acceptsMimeTypes([
                'image/jpeg',
                'image/png',
                'image/webp',
            ]);
    }
}
