<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserTierListRow extends Model
{
    /**
     * Cores aceitas no select de cor da row. Mapeadas para classes Tailwind no frontend.
     */
    public const COLORS = ['rose', 'orange', 'amber', 'emerald', 'sky', 'violet', 'fuchsia', 'zinc'];

    protected $fillable = [
        'user_tier_list_id',
        'label',
        'color',
        'position',
    ];

    protected $casts = [
        'position' => 'integer',
    ];

    public function tierList(): BelongsTo
    {
        return $this->belongsTo(UserTierList::class, 'user_tier_list_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(UserTierListItem::class)->orderBy('position');
    }
}
