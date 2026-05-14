<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RosterPowerSnapshot extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'snapshot_date',
        'total_power',
        'hero_count',
        'top_hero_id',
        'top_hero_power',
        'created_at',
    ];

    protected $casts = [
        // Formato Y-m-d explícito pra que o updateOrCreate compare igual ao toDateString().
        // Cast genérico 'date' converte pra datetime em alguns drivers (SQLite text).
        'snapshot_date' => 'date:Y-m-d',
        'total_power' => 'integer',
        'hero_count' => 'integer',
        'top_hero_power' => 'integer',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function topHero(): BelongsTo
    {
        return $this->belongsTo(Hero::class, 'top_hero_id');
    }
}
