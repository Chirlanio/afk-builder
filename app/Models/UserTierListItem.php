<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserTierListItem extends Model
{
    protected $fillable = [
        'user_tier_list_row_id',
        'hero_id',
        'position',
    ];

    protected $casts = [
        'position' => 'integer',
    ];

    public function row(): BelongsTo
    {
        return $this->belongsTo(UserTierListRow::class, 'user_tier_list_row_id');
    }

    public function hero(): BelongsTo
    {
        return $this->belongsTo(Hero::class);
    }
}
