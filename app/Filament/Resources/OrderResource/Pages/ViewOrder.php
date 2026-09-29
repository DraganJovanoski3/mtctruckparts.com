<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Order')->schema([
                Infolists\Components\TextEntry::make('number'),
                Infolists\Components\TextEntry::make('email'),
                Infolists\Components\TextEntry::make('status'),
                Infolists\Components\TextEntry::make('payment_status'),
                Infolists\Components\TextEntry::make('grand_total')->money('USD'),
                Infolists\Components\TextEntry::make('discount_total')->money('USD'),
                Infolists\Components\TextEntry::make('coupon_code')->placeholder('—'),
            ])->columns(2),
            Infolists\Components\Section::make('Shipping')->schema([
                Infolists\Components\TextEntry::make('shipping_address')->formatStateUsing(fn ($state) => is_array($state) ? collect($state)->map(fn ($v, $k) => "$k: $v")->implode("\n") : $state)->markdown(),
            ]),
        ]);
    }
}
