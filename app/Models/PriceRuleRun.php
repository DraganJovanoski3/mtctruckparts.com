<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PriceRuleRun extends Model
{
    protected $fillable = [
        'price_rule_id', 'user_id', 'status', 'total_chunks',
        'completed_chunks', 'affected_rows', 'error_message',
        'started_at', 'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(PriceRule::class, 'price_rule_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function changes(): HasMany
    {
        return $this->hasMany(PriceChangeLog::class);
    }

    public function progressPercent(): int
    {
        if ($this->total_chunks === 0) {
            return 0;
        }

        return (int) floor(($this->completed_chunks / $this->total_chunks) * 100);
    }
}
