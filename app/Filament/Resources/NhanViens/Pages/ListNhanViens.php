<?php

namespace App\Filament\Resources\NhanViens\Pages;

use App\Filament\Resources\NhanViens\NhanVienResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListNhanViens extends ListRecords
{
    protected static string $resource = NhanVienResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
