<?php

namespace App\Filament\Resources\JobLois;

use App\Filament\Resources\JobLois\Pages\ListJobLois;
use App\Filament\Resources\JobLois\Pages\ViewJobLoi;
use App\Models\FailedJob;
use App\Support\NhatKy;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Artisan;
use UnitEnum;

/**
 * Job lỗi (bảng failed_jobs, sau 3 lần thử): xem lỗi, chạy lại (đưa job về hàng đợi, queue:retry) hoặc bỏ (queue:forget).
 * Mỗi lần chạy lại, bỏ ghi nhật ký thao tác.
 */
class JobLoiResource extends Resource
{
    protected static ?string $model = FailedJob::class;

    protected static ?string $slug = 'job-loi';

    protected static ?string $modelLabel = 'job lỗi';

    protected static ?string $pluralModelLabel = 'Job lỗi';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static string|UnitEnum|null $navigationGroup = 'Hệ thống';

    protected static ?int $navigationSort = 50;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('settings.manage') ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    /** Đưa job về hàng đợi (job chạy lại ở lần chạy hàng đợi kế tiếp), xoá khỏi danh sách job lỗi. */
    public static function chayLai(FailedJob $job): void
    {
        NhatKy::ghi('failed_job.retry', $job, [], ['uuid' => $job->uuid, 'job' => $job->tenJob()]);
        Artisan::call('queue:retry', ['id' => [$job->uuid]]);
    }

    public static function boQua(FailedJob $job): void
    {
        NhatKy::ghi('failed_job.forget', $job, ['uuid' => $job->uuid, 'job' => $job->tenJob(), 'exception' => $job->dongLoi()], []);
        Artisan::call('queue:forget', ['id' => $job->uuid]);
    }

    public static function nutChayLai(): Action
    {
        return Action::make('chay-lai')->label('Chạy lại')->icon(Heroicon::OutlinedArrowPath)
            ->requiresConfirmation()
            ->modalHeading('Chạy lại job này?')
            ->modalDescription('Job được đưa về hàng đợi và chạy ở lần chạy hàng đợi kế tiếp (mỗi phút).')
            ->action(function (FailedJob $record): void {
                self::chayLai($record);
                Notification::make()->title('Đã đưa job về hàng đợi')->success()->send();
            });
    }

    public static function nutBoQua(): Action
    {
        return Action::make('bo-qua')->label('Bỏ')->icon(Heroicon::OutlinedTrash)->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Bỏ job lỗi này?')
            ->modalDescription('Job không chạy lại nữa và bị xoá khỏi danh sách. Nhật ký thao tác giữ lại dòng lỗi.')
            ->action(function (FailedJob $record): void {
                self::boQua($record);
                Notification::make()->title('Đã bỏ job lỗi')->success()->send();
            });
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextEntry::make('failed_at')->label('Lỗi lúc')->dateTime('d/m/Y H:i:s'),
            TextEntry::make('ten_job')->label('Job')->state(fn (FailedJob $record) => $record->tenJob())->placeholder('—'),
            TextEntry::make('queue')->label('Hàng đợi'),
            TextEntry::make('uuid')->label('Mã'),
            TextEntry::make('exception')->label('Lỗi')->columnSpanFull()->extraAttributes(['class' => 'font-mono whitespace-pre-wrap text-sm']),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('failed_at')->label('Lỗi lúc')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('ten_job')->label('Job')->state(fn (FailedJob $record) => $record->tenJob()),
                TextColumn::make('queue')->label('Hàng đợi'),
                TextColumn::make('dong_loi')->label('Lỗi')->state(fn (FailedJob $record) => $record->dongLoi())->limit(100),
            ])
            ->defaultSort('id', 'desc')
            ->emptyStateHeading('Không có job lỗi nào')
            ->recordActions([ViewAction::make(), self::nutChayLai(), self::nutBoQua()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListJobLois::route('/'),
            'view' => ViewJobLoi::route('/{record}'),
        ];
    }
}
