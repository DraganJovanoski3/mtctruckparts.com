<?php

namespace App\Filament\Resources\CouponResource\Pages;

use App\Filament\Resources\CouponResource;
use App\Models\Coupon;
use Filament\Resources\Pages\CreateRecord;

class CreateCoupon extends CreateRecord
{
    protected static string $resource = CouponResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (! empty($data['is_newsletter_welcome'])) {
            Coupon::query()->where('is_newsletter_welcome', true)->update(['is_newsletter_welcome' => false]);
        }

        return $data;
    }
}
