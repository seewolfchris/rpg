<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CampaignGmContactThread extends Model
{
    /** @use HasFactory<\Illuminate\Database\Eloquent\Factories\Factory> */
    use HasFactory;

    public const STATUS_OPEN = 'open';

    public const STATUS_WAITING_FOR_GM = 'waiting_for_gm';

    public const STATUS_WAITING_FOR_PLAYER = 'waiting_for_player';

    public const STATUS_CLOSED = 'closed';

    /**
     * @var array<string, string>
     */
    public const STATUS_LABELS = [
        self::STATUS_OPEN => 'Offen',
        self::STATUS_WAITING_FOR_GM => 'Wartet auf Spielleitung',
        self::STATUS_WAITING_FOR_PLAYER => 'Wartet auf Spieler',
        self::STATUS_CLOSED => 'Geschlossen',
    ];

    /**
     * @var list<string>
     */
    public const ALL_STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_WAITING_FOR_GM,
        self::STATUS_WAITING_FOR_PLAYER,
        self::STATUS_CLOSED,
    ];

    /**
     * @var list<string>
     */
    public const MANUAL_STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_CLOSED,
    ];

    /**
     * @var list<string>
     */
    public const PANEL_RELATIONS = [
        'campaign.invitations',
        'creator',
        'character',
        'scene',
        'latestMessage.user',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'campaign_id',
        'created_by',
        'subject',
        'status',
        'character_id',
        'scene_id',
        'last_activity_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'campaign_id' => 'integer',
            'created_by' => 'integer',
            'character_id' => 'integer',
            'scene_id' => 'integer',
            'last_activity_at' => 'datetime',
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
     * @return BelongsTo<Character, $this>
     */
    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    /**
     * @return BelongsTo<Scene, $this>
     */
    public function scene(): BelongsTo
    {
        return $this->belongsTo(Scene::class);
    }

    /**
     * @return HasMany<CampaignGmContactMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(CampaignGmContactMessage::class, 'thread_id');
    }

    /**
     * @return HasOne<CampaignGmContactMessage, $this>
     */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(CampaignGmContactMessage::class, 'thread_id')
            ->latestOfMany('created_at');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForCampaign(Builder $query, Campaign|int $campaign): Builder
    {
        $campaignId = $campaign instanceof Campaign ? (int) $campaign->id : (int) $campaign;

        return $query->where('campaign_id', $campaignId);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOrderedByActivity(Builder $query): Builder
    {
        return $query
            ->orderByDesc('last_activity_at')
            ->orderByDesc('id');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeVisibleTo(Builder $query, User $user, Campaign $campaign): Builder
    {
        $query->where('campaign_id', (int) $campaign->id);

        if (! self::hasCampaignContactAccess($campaign, $user)) {
            return $query->whereRaw('1 = 0');
        }

        if (self::isGmSide($campaign, $user)) {
            return $query;
        }

        return $query->where('created_by', (int) $user->id);
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    public function statusLabel(): string
    {
        return self::statusLabelFor((string) $this->status);
    }

    public static function statusLabelFor(string $status): string
    {
        return self::STATUS_LABELS[$status] ?? $status;
    }

    public static function hasCampaignContactAccess(Campaign $campaign, User $user): bool
    {
        if ($user->hasRole(UserRole::ADMIN)) {
            return true;
        }

        if ((int) $campaign->owner_id === (int) $user->id) {
            return true;
        }

        return $campaign->hasAcceptedInvitation($user);
    }

    public static function isGmSide(Campaign $campaign, User $user): bool
    {
        if ($user->hasRole(UserRole::ADMIN)) {
            return true;
        }

        if ((int) $campaign->owner_id === (int) $user->id) {
            return true;
        }

        return $campaign->isCoGm($user);
    }
}
