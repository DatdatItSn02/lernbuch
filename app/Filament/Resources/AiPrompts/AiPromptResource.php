<?php

namespace App\Filament\Resources\AiPrompts;

use App\Filament\Resources\AiPrompts\Pages\ListAiPrompts;
use App\Filament\Resources\AiPrompts\Pages\ViewAiPrompt;
use App\Models\AiPrompt;
use App\Support\QuanTri\PromptAi;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Prompt AI theo phiên bản (cần ai.manage). Không sửa tại chỗ, không xoá: sửa là tạo phiên bản mới ở trang xem một bản,
 * chạy thử, rồi "Dùng bản này"; bản đang dùng có nút "Quay lại bản trước". Việc xử lý ở App\Support\QuanTri\PromptAi.
 */
class AiPromptResource extends Resource
{
    protected static ?string $model = AiPrompt::class;

    protected static ?string $slug = 'ai-prompt';

    protected static ?string $modelLabel = 'phiên bản prompt';

    protected static ?string $pluralModelLabel = 'Prompt AI';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCommandLine;

    protected static string|UnitEnum|null $navigationGroup = 'Hệ thống';

    protected static ?int $navigationSort = 32;

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

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            TextEntry::make('kind')->label('Loại')->formatStateUsing(fn (string $state) => PromptAi::tenLoai($state).' ('.$state.')'),
            TextEntry::make('version')->label('Phiên bản'),
            TextEntry::make('is_active')->label('Đang dùng')->formatStateUsing(fn (bool $state) => $state ? 'Đang dùng' : 'Không'),
            TextEntry::make('model')->label('Model lúc tạo'),
            TextEntry::make('max_tokens')->label('Token tối đa'),
            TextEntry::make('temperature')->label('Nhiệt độ'),
            TextEntry::make('system_prompt')->label('Prompt hệ thống')->columnSpanFull(),
            TextEntry::make('user_template')->label('Mẫu câu')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $q) => $q->whereNull('deleted_at')->orderBy('kind')->orderByDesc('version'))
            ->columns([
                TextColumn::make('kind')->label('Loại')->formatStateUsing(fn (string $state) => PromptAi::tenLoai($state)),
                TextColumn::make('version')->label('Phiên bản'),
                TextColumn::make('is_active')->label('Đang dùng')->formatStateUsing(fn (bool $state) => $state ? 'Đang dùng' : '')->placeholder(''),
                TextColumn::make('model')->label('Model'),
                TextColumn::make('max_tokens')->label('Token tối đa'),
                TextColumn::make('temperature')->label('Nhiệt độ'),
                TextColumn::make('created_at')->label('Tạo lúc')->dateTime('d/m/Y H:i'),
            ])
            ->filters([
                SelectFilter::make('kind')->label('Loại')->options(PromptAi::LOAI),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAiPrompts::route('/'),
            'view' => ViewAiPrompt::route('/{record}'),
        ];
    }
}
