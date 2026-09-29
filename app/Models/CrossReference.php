<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrossReference extends Model
{
    protected $fillable = ['product_id', 'ref_number', 'ref_type'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
