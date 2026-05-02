<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncLog extends Model
{
    protected $fillable = [
        'source',
        'status',
        'started_at',
        'finished_at',
        'heroes_seen',
        'heroes_created',
        'heroes_updated',
        'new_hero_slugs',
        'error_message',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'heroes_seen' => 'integer',
        'heroes_created' => 'integer',
        'heroes_updated' => 'integer',
        'new_hero_slugs' => 'array',
    ];
}
