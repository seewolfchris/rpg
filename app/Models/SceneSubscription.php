<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class SceneSubscription extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'scene_id',
        'user_id',
        'is_muted',
        'last_read_post_id',
        'last_read_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_muted' => 'boolean',
            'last_read_post_id' => 'integer',
            'last_read_at' => 'datetime',
        ];
    }

    public function scene(): BelongsTo
    {
        return $this->belongsTo(Scene::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lastReadPost(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'last_read_post_id');
    }

    public function hasUnread(?int $latestPostId): bool
    {
        if (! $latestPostId) {
            return false;
        }

        return (int) ($this->last_read_post_id ?? 0) < $latestPostId;
    }

    public function markRead(?int $latestPostId = null): void
    {
        if (! $latestPostId) {
            return;
        }

        $this->last_read_post_id = $latestPostId;
        $this->last_read_at = Carbon::now();
        $this->save();
    }

    public function markUnread(): void
    {
        $this->last_read_post_id = null;
        $this->last_read_at = null;
        $this->save();
    }
}
