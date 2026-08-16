<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class UserTierList extends Model
{
    public const SHARE_TOKEN_LENGTH = 32;

    protected $fillable = [
        'user_id',
        'game_account_id',
        'name',
        'description',
        'share_token',
        'is_template',
        'cloned_from_id',
    ];

    protected $casts = [
        'is_template' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function gameAccount(): BelongsTo
    {
        return $this->belongsTo(GameAccount::class);
    }

    public function rows(): HasMany
    {
        return $this->hasMany(UserTierListRow::class)->orderBy('position');
    }

    public function clonedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'cloned_from_id');
    }

    public function generateShareToken(): string
    {
        do {
            $token = Str::random(self::SHARE_TOKEN_LENGTH);
        } while (self::where('share_token', $token)->exists());

        $this->forceFill(['share_token' => $token])->save();

        return $token;
    }

    public function revokeShareToken(): void
    {
        $this->forceFill(['share_token' => null])->save();
    }
}
