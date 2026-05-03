<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserHero extends Model
{
    protected $fillable = [
        'user_id',
        'hero_id',
        'level',
        'ascension_code',
        'ascension_value',
        'signature_item',
        'furniture',
        'engraving',
        'artifact_id',
        'artifact_star_level',
        'power_rating',
        'notes',
    ];

    protected $casts = [
        'level' => 'integer',
        'ascension_value' => 'integer',
        'signature_item' => 'integer',
        'furniture' => 'integer',
        'engraving' => 'integer',
        'artifact_star_level' => 'integer',
        'power_rating' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hero(): BelongsTo
    {
        return $this->belongsTo(Hero::class);
    }

    public function artifact(): BelongsTo
    {
        return $this->belongsTo(Artifact::class);
    }
}
