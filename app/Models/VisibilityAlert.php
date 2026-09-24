<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisibilityAlert extends Model
{
    protected $fillable = [
        'user_id',
        'object_id',
        'latitude',
        'longitude',
        'active',
        'last_state_up_after_dark',
        'last_checked_at',
        'last_triggered_at',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:2',
            'longitude' => 'decimal:2',
            'active' => 'boolean',
            'last_state_up_after_dark' => 'boolean',
            'last_checked_at' => 'datetime',
            'last_triggered_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
