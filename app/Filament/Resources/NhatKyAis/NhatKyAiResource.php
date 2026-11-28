<?php

namespace App\Filament\Resources\NhatKyAis;

use App\Filament\Resources\NhatKyAis\Pages\ListNhatKyAis;
use App\Filament\Resources\NhatKyAis\Pages\ViewNhatKyAi;
use App\Models\AiRequest;
use App\Support\QuanTri\PromptAi;
use App\Support\QuanTri\ThongKeAi;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\PaginationMode;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/** Nhật ký AI (cần ai.manage, chỉ xem): từng lượt gọi, kể cả lượt bị chặn, lấy từ cache, chạy thử của quản trị. */
class NhatKyAiResource extends Resource
{
    public const TRANG_THAI = ['ok' => 'Thành công', 'error' => 'Lỗi', 'quota' => 'Hết lượt', 'blocked' => 'Bị chặn'];

    protected static ?string $model = AiRequest::class;

    protected static ?string $slug = 'nhat-ky-ai';

    protected static ?string $modelLabel = 'lượt gọi AI';

    protected static ?string $pluralModelLabel = 'Nhật ký AI';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedListBullet;

    protected static string|UnitEnum|null $navigationGroup = 'Hệ thống';

    protected static ?int $navigationSort = 34;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('ai.manage') ?? false;
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

    public static function luc(mixed $giay): string
    {
        return is_numeric($giay) ? CarbonImmutable::createFromTimestamp((int) $giay, 'Asia/Ho_Chi_Minh')->format('d/m/Y H:i:s') : '—';
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            TextEntry::make('created_at')->label('Lúc (giờ Việt Nam)')->formatStateUsing(fn ($state) => self::luc($state)),
            TextEntry::make('user.email')->label('Người học')->placeholder('Chạy thử của quản trị'),
            TextEntry::make('kind')->label('Loại')->formatStateUsing(fn (string $state) => PromptAi::tenLoai($state)),
            TextEntry::make('status')->label('Trạng thái')->formatStateUsing(fn (string $state) => self::TRANG_THAI[$state] ?? $state),
            TextEntry::make('cache_hit')->label('Từ cache')->formatStateUsing(fn (bool $state) => $state ? 'Có' : 'Không'),
            TextEntry::make('model')->label('Model')->placeholder('—'),
            TextEntry::make('input_tokens')->label('Token vào'),
            TextEntry::make('output_tokens')->label('Token ra'),
            TextEntry::make('cost_micro_usd')->label('Chi phí (USD)')->formatStateUsing(fn (int $state) => ThongKeAi::usd($state)),
            TextEntry::make('latency_ms')->label('Chờ (ms)')->placeholder('—'),
            TextEntry::make('context_type')->label('Ngữ cảnh')->formatStateUsing(fn (string $state, AiRequest $record) => $state.' #'.$record->getAttribute('context_id'))->placeholder('—'),
            TextEntry::make('input_excerpt')->label('Đoạn trích (xoá sau 30 ngày)')->placeholder('—')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $q) => $q->with('user:id,email'))
            ->paginationMode(PaginationMode::Simple)
            ->columns([
                TextColumn::make('created_at')->label('Lúc')->formatStateUsing(fn ($state) => self::luc($state)),
                TextColumn::make('user.email')->label('Người học')->placeholder('Chạy thử'),
                TextColumn::make('kind')->label('Loại')->formatStateUsing(fn (string $state) => PromptAi::tenLoai($state)),
                TextColumn::make('status')->label('Trạng thái')->formatStateUsing(fn (string $state) => self::TRANG_THAI[$state] ?? $state),
                TextColumn::make('cache_hit')->label('Cache')->formatStateUsing(fn (bool $state) => $state ? 'Có' : ''),
                TextColumn::make('model')->label('Model')->placeholder('—'),
                TextColumn::make('token')->label('Token')->state(fn (AiRequest $record) => $record->getAttribute('input_tokens') + $record->getAttribute('output_tokens')),
                TextColumn::make('cost_micro_usd')->label('Chi phí (USD)')->formatStateUsing(fn (int $state) => ThongKeAi::usd($state)),
                TextColumn::make('latency_ms')->label('Chờ (ms)')->placeholder('—'),
                TextColumn::make('input_excerpt')->label('Đoạn trích')->limit(80)->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('kind')->label('Loại')->options(PromptAi::LOAI + ['pron' => 'Chấm phát âm']),
                SelectFilter::make('status')->label('Trạng thái')->options(self::TRANG_THAI),
                Filter::make('ngay')
                    ->schema([DatePicker::make('tu')->label('Từ ngày'), DatePicker::make('den')->label('Đến ngày')])
                    ->query(fn (Builder $q, array $data) => $q
                        ->when($data['tu'] ?? null, fn (Builder $q, $tu) => $q->where('created_at', '>=', CarbonImmutable::parse((string) $tu, 'Asia/Ho_Chi_Minh')->startOfDay()->getTimestamp()))
                        ->when($data['den'] ?? null, fn (Builder $q, $den) => $q->where('created_at', '<', CarbonImmutable::parse((string) $den, 'Asia/Ho_Chi_Minh')->startOfDay()->addDay()->getTimestamp()))),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([ViewAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNhatKyAis::route('/'),
            'view' => ViewNhatKyAi::route('/{record}'),
        ];
    }
}
