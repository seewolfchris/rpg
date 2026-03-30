<?php

namespace App\Models;

use App\Support\PostContentRenderer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

class Post extends Model
{
    /** @use HasFactory<\Database\Factories\PostFactory> */
    use HasFactory;

    public const THREAD_POSTS_PER_PAGE = 20;

    /**
     * @var list<string>
     */
    public const WORLD_CONTEXT_RELATIONS = [
        'scene.campaign.world',
    ];

    /**
     * @var list<string>
     */
    public const SCENE_CONTEXT_RELATIONS = [
        'scene.campaign',
        'scene',
    ];

    /**
     * @var list<string>
     */
    public const THREAD_PAGE_RELATIONS = [
        'scene.campaign',
        'user',
        'character',
        'approvedBy',
        'pinnedBy',
        'revisions.editor',
        'moderationLogs.moderator',
        'diceRoll.character.user',
        'reactions',
    ];

    /**
     * @var list<string>
     */
    public const THREAD_ITEM_RELATIONS = [
        ...self::WORLD_CONTEXT_RELATIONS,
        ...self::THREAD_PAGE_RELATIONS,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'scene_id',
        'user_id',
        'character_id',
        'post_type',
        'content_format',
        'content',
        'meta',
        'moderation_status',
        'approved_at',
        'approved_by',
        'is_edited',
        'edited_at',
        'is_pinned',
        'pinned_at',
        'pinned_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'approved_at' => 'datetime',
            'edited_at' => 'datetime',
            'is_edited' => 'boolean',
            'is_pinned' => 'boolean',
            'pinned_at' => 'datetime',
        ];
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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Character, $this>
     */
    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function pinnedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pinned_by');
    }

    /**
     * @return HasMany<PostRevision, $this>
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(PostRevision::class)->orderByDesc('version');
    }

    /**
     * @return HasMany<PostModerationLog, $this>
     */
    public function moderationLogs(): HasMany
    {
        return $this->hasMany(PostModerationLog::class)->orderByDesc('created_at');
    }

    /**
     * @return HasMany<PostReaction, $this>
     */
    public function reactions(): HasMany
    {
        return $this->hasMany(PostReaction::class);
    }

    /**
     * @return HasMany<PostMention, $this>
     */
    public function mentionRecords(): HasMany
    {
        return $this->hasMany(PostMention::class);
    }

    /**
     * @return HasOne<DiceRoll, $this>
     */
    public function diceRoll(): HasOne
    {
        return $this->hasOne(DiceRoll::class);
    }

    /**
     * @return HasOne<PostModerationLog, $this>
     */
    public function latestModerationLog(): HasOne
    {
        return $this->hasOne(PostModerationLog::class)->latestOfMany('created_at');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeLatestByIdHotpath(Builder $query): Builder
    {
        $driver = DB::connection($query->getModel()->getConnectionName())->getDriverName();
        $forceIndexEnabled = (bool) config('performance.posts_latest_by_id.force_index_enabled', false);
        $indexName = (string) config('performance.posts_latest_by_id.force_index_name', 'posts_scene_id_id_idx');

        if ($forceIndexEnabled && in_array($driver, ['mysql', 'mariadb'], true) && preg_match('/^[A-Za-z0-9_]+$/', $indexName) === 1) {
            $query->from(DB::raw($this->getTable().' FORCE INDEX ('.$indexName.')'));
        }

        return $query->orderByDesc('id');
    }

    public function renderedContent(): HtmlString
    {
        return app(PostContentRenderer::class)->render($this->content, $this->content_format);
    }
}
