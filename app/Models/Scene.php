<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Scene extends Model
{
    use HasFactory;

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

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function previousScene(): BelongsTo
    {
        return $this->belongsTo(Scene::class, 'previous_scene_id');
    }

    public function diceRolls(): HasMany
    {
        return $this->hasMany(DiceRoll::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(SceneSubscription::class);
    }

    public function bookmarks(): HasMany
    {
        return $this->hasMany(SceneBookmark::class);
    }

    public function subscribers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'scene_subscriptions')
            ->withPivot(['is_muted', 'last_read_post_id', 'last_read_at'])
            ->withTimestamps();
    }
}
