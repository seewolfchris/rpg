<?php

namespace App\Models;

use App\Enums\CampaignMembershipRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $campaign_id
 * @property int $user_id
 * @property CampaignMembershipRole|string $role
 * @property int|null $assigned_by
 * @property-read Campaign $campaign
 * @property-read User $user
 * @property-read User|null $assigner
 */
class CampaignMembership extends Model
{
    /** @use HasFactory<\Database\Factories\CampaignMembershipFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'campaign_id',
        'user_id',
        'role',
        'assigned_by',
        'assigned_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'campaign_id' => 'integer',
            'user_id' => 'integer',
            'assigned_by' => 'integer',
            'assigned_at' => 'datetime',
            'role' => CampaignMembershipRole::class,
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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function hasRole(CampaignMembershipRole|string $role): bool
    {
        $roleValue = $role instanceof CampaignMembershipRole ? $role->value : $role;
        $currentRole = $this->role;
        $currentRoleValue = $currentRole instanceof CampaignMembershipRole
            ? $currentRole->value
            : (is_string($currentRole) ? $currentRole : null);

        return $currentRoleValue === $roleValue;
    }
}
