<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostSceneNotificationDelivery extends Model
{
    /** @use HasFactory<\Illuminate\Database\Eloquent\Factories\Factory> */
    use HasFactory;

    public const CHANNEL_DATABASE = 'database';

    public const CHANNEL_WEBPUSH = 'webpush';

    public const STATUS_PENDING = 'pending';

    public const STATUS_SENDING = 'sending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'post_id',
        'recipient_user_id',
        'channel',
        'status',
        'attempt_count',
        'first_attempted_at',
        'last_attempted_at',
        'sent_at',
        'last_error',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'post_id' => 'integer',
            'recipient_user_id' => 'integer',
            'attempt_count' => 'integer',
            'first_attempted_at' => 'datetime',
            'last_attempted_at' => 'datetime',
            'sent_at' => 'datetime',
            'last_error' => 'string',
        ];
    }

    /**
     * @return BelongsTo<Post, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }
}

