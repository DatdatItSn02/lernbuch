<?php

namespace App\Filament\Resources\Nguons\Pages;

use App\Filament\Resources\Nguons\NguonResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListNguons extends ListRecords
{
    protected static string $resource = NguonResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
