<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Handout extends Model implements HasMedia
{
    /** @use HasFactory<\Database\Factories\HandoutFactory> */
    use HasFactory;
    use InteractsWithMedia;

    public const HANDOUT_FILE_COLLECTION = 'handout_file';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'campaign_id',
        'scene_id',
        'created_by',
        'updated_by',
        'title',
        'description',
        'revealed_at',
        'version_label',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'campaign_id' => 'integer',
            'scene_id' => 'integer',
            'created_by' => 'integer',
            'updated_by' => 'integer',
            'sort_order' => 'integer',
            'revealed_at' => 'datetime',
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
     * @return BelongsTo<Scene, $this>
     */
    public function scene(): BelongsTo
    {
        return $this->belongsTo(Scene::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function isRevealed(): bool
    {
        return $this->revealed_at !== null;
    }

    public function registerMediaCollections(): void
    {
        $this
            ->addMediaCollection(self::HANDOUT_FILE_COLLECTION)
            ->useDisk('local')
            ->singleFile()
            ->acceptsMimeTypes([
                'image/jpeg',
                'image/png',
                'image/webp',
            ]);
    }
}
