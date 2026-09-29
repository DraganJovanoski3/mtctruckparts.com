<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PriceRule extends Model
{
    protected $fillable = [
        'name', 'scope_type', 'scope_ids', 'formula_type',
        'percent', 'round_to', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'scope_ids' => 'array',
            'percent' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    public function runs(): HasMany
    {
        return $this->hasMany(PriceRuleRun::class);
    }
}
