<?php

namespace App\Filament\Resources\JobLois\Pages;

use App\Filament\Resources\JobLois\JobLoiResource;
use Filament\Resources\Pages\ViewRecord;

class ViewJobLoi extends ViewRecord
{
    protected static string $resource = JobLoiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            JobLoiResource::nutChayLai()->successRedirectUrl(JobLoiResource::getUrl()),
            JobLoiResource::nutBoQua()->successRedirectUrl(JobLoiResource::getUrl()),
        ];
    }
}
