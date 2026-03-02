<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Character extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'epithet',
        'bio',
        'avatar_path',
        'strength',
        'dexterity',
        'constitution',
        'intelligence',
        'wisdom',
        'charisma',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'strength' => 'integer',
            'dexterity' => 'integer',
            'constitution' => 'integer',
            'intelligence' => 'integer',
            'wisdom' => 'integer',
            'charisma' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function diceRolls(): HasMany
    {
        return $this->hasMany(DiceRoll::class);
    }

    public function avatarUrl(): string
    {
        if ($this->avatar_path) {
            return asset('storage/'.$this->avatar_path);
        }

        return asset('images/character-placeholder.svg');
    }
}
