<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    protected $fillable = [
        'code', 'description', 'discount_type', 'amount', 'free_shipping', 'individual_use',
        'minimum_amount', 'maximum_amount', 'usage_limit', 'usage_limit_per_user', 'used_count',
        'limit_usage_to_x_items', 'product_ids', 'exclude_product_ids', 'category_ids',
        'exclude_category_ids', 'email_restrictions', 'exclude_sale_items', 'is_active',
        'is_newsletter_welcome', 'starts_at', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'minimum_amount' => 'decimal:2',
            'maximum_amount' => 'decimal:2',
            'free_shipping' => 'boolean',
            'individual_use' => 'boolean',
            'exclude_sale_items' => 'boolean',
            'is_active' => 'boolean',
            'is_newsletter_welcome' => 'boolean',
            'product_ids' => 'array',
            'exclude_product_ids' => 'array',
            'category_ids' => 'array',
            'exclude_category_ids' => 'array',
            'email_restrictions' => 'array',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function subscribers(): HasMany
    {
        return $this->hasMany(NewsletterSubscriber::class);
    }

    public function setCodeAttribute(?string $value): void
    {
        $this->attributes['code'] = $value ? strtoupper(trim($value)) : null;
    }

    public function label(): string
    {
        return match ($this->discount_type) {
            'percent' => rtrim(rtrim(number_format((float) $this->amount, 2), '0'), '.').'% off',
            'fixed_cart' => '$'.number_format((float) $this->amount, 2).' off cart',
            'fixed_product' => '$'.number_format((float) $this->amount, 2).' off product',
            default => (string) $this->code,
        };
    }
}
