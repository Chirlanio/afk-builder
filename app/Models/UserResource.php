<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserResource extends Model
{
    public const FIELDS = [
        'primordial_emblems',
        'amplifying_emblems',
        'faction_emblems',
        'mythic_furniture',
        'engraving_scrolls',
    ];

    protected $fillable = [
        'user_id',
        'primordial_emblems',
        'amplifying_emblems',
        'faction_emblems',
        'mythic_furniture',
        'engraving_scrolls',
    ];

    protected $casts = [
        'primordial_emblems' => 'integer',
        'amplifying_emblems' => 'integer',
        'faction_emblems' => 'integer',
        'mythic_furniture' => 'integer',
        'engraving_scrolls' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
