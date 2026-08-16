<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class FormationTag extends Model
{
    public const COLORS = [
        'amber',
        'sky',
        'emerald',
        'rose',
        'violet',
        'orange',
        'fuchsia',
        'slate',
    ];

    public const DEFAULT_COLOR = 'amber';

    protected $fillable = [
        'user_id',
        'name',
        'color',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function gameAccount(): BelongsTo
    {
        return $this->belongsTo(GameAccount::class);
    }

    public function formations(): BelongsToMany
    {
        return $this->belongsToMany(
            Formation::class,
            'formation_tag_assignments',
            'formation_tag_id',
            'formation_id',
        )->withTimestamps();
    }
}
