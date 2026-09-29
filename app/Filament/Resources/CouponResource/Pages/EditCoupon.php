<?php

namespace App\Filament\Resources\CouponResource\Pages;

use App\Filament\Resources\CouponResource;
use App\Models\Coupon;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCoupon extends EditRecord
{
    protected static string $resource = CouponResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (! empty($data['is_newsletter_welcome'])) {
            Coupon::query()
                ->where('is_newsletter_welcome', true)
                ->where('id', '!=', $this->record->id)
                ->update(['is_newsletter_welcome' => false]);
        }

        return $data;
    }
}
