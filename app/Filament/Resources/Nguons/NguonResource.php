<?php

namespace App\Filament\Resources\Nguons;

use App\Filament\QuyenNoiDung;
use App\Filament\Resources\Nguons\Pages\CreateNguon;
use App\Filament\Resources\Nguons\Pages\EditNguon;
use App\Filament\Resources\Nguons\Pages\ListNguons;
use App\Models\ContentSource;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use UnitEnum;

/**
 * Nguồn nội dung và giấy phép: mỗi thẻ, câu, bài ghi nguồn để truy lại quyền dùng. Chỉ nguồn "được bán" hoặc "đã mua quyền"
 * mới dùng nội dung để bán (ContentSource::DUOC_BAN). Thêm nguồn mới ở đây trước khi nhập nội dung từ nguồn đó.
 */
class NguonResource extends Resource
{
    public const QUYEN = [
        'allowed' => 'Được dùng để bán',
        'licensed' => 'Đã mua quyền',
        'forbidden' => 'Không được bán',
        'unknown' => 'Chưa rõ',
    ];

    protected static ?string $model = ContentSource::class;

    protected static ?string $slug = 'nguon';

    protected static ?string $modelLabel = 'nguồn';

    protected static ?string $pluralModelLabel = 'Nguồn và giấy phép';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static string|UnitEnum|null $navigationGroup = 'Nội dung';

    protected static ?int $navigationSort = 6;

    protected static ?string $recordTitleAttribute = 'name';

    public static function canAccess(): bool
    {
        return QuyenNoiDung::sua();
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('code')->label('Mã')->required()->maxLength(40)->regex('/^[a-z0-9][a-z0-9_-]*$/')
                ->unique(table: 'content_sources', column: 'code', ignoreRecord: true)
                ->disabledOn('edit')
                ->helperText('Chữ thường, số, gạch nối; không đổi sau khi tạo (file nhập ghi nguồn bằng mã này).'),
            TextInput::make('name')->label('Tên nguồn')->required()->maxLength(160),
            TextInput::make('license')->label('Giấy phép')->maxLength(60)->placeholder('vd CC BY 2.0 FR, CC0, tự soạn'),
            Select::make('commercial_use')->label('Dùng để bán')->options(self::QUYEN)->required()->default('unknown')->selectablePlaceholder(false),
            TextInput::make('url')->label('Địa chỉ')->url()->maxLength(255)->columnSpanFull(),
            Textarea::make('attribution')->label('Ghi công (hiện kèm nội dung)')->rows(2)->maxLength(2000)->columnSpanFull(),
            Textarea::make('notes')->label('Ghi chú')->rows(3)->maxLength(5000)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->addSelect([
                'so_the' => DB::table('cards')->selectRaw('COUNT(*)')->whereColumn('cards.source_id', 'content_sources.id')->whereNull('cards.deleted_at'),
            ]))
            ->columns([
                TextColumn::make('name')->label('Nguồn')->searchable(['name', 'code'])->sortable()->description(fn (ContentSource $record) => $record->code),
                TextColumn::make('license')->label('Giấy phép')->placeholder('—'),
                TextColumn::make('commercial_use')->label('Dùng để bán')->badge()
                    ->formatStateUsing(fn (string $state) => self::QUYEN[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'allowed', 'licensed' => 'success',
                        'forbidden' => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('so_the')->label('Số thẻ')->numeric()->sortable(),
            ])
            ->filters([
                SelectFilter::make('commercial_use')->label('Dùng để bán')->options(self::QUYEN),
            ])
            ->defaultSort('code')
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNguons::route('/'),
            'create' => CreateNguon::route('/create'),
            'edit' => EditNguon::route('/{record}/edit'),
        ];
    }
}
