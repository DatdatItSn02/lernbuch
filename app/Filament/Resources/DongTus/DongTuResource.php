<?php

namespace App\Filament\Resources\DongTus;

use App\Filament\QuyenNoiDung;
use App\Filament\Resources\DongTus\Pages\EditDongTu;
use App\Filament\Resources\DongTus\Pages\ListDongTus;
use App\Models\Verb;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Động từ và bảng chia: sửa nghĩa, các dạng chính, bảng Präsens, Präteritum, Perfekt (6 ngôi) và mệnh lệnh (3 dạng).
 * Luyện chia động từ chấm theo bảng này nên sửa có hiệu lực ngay. Bảng nào để trống cả thì không lưu bảng đó.
 */
class DongTuResource extends Resource
{
    public const NGOI = ['ich', 'du', 'er/sie/es', 'wir', 'ihr', 'sie/Sie'];

    public const MENH_LENH = ['du', 'ihr', 'Sie'];

    public const TEN_BANG = ['pres' => 'Präsens', 'prat' => 'Präteritum', 'perf' => 'Perfekt', 'imp' => 'Imperativ (mệnh lệnh)'];

    protected static ?string $model = Verb::class;

    protected static ?string $slug = 'dong-tu';

    protected static ?string $modelLabel = 'động từ';

    protected static ?string $pluralModelLabel = 'Động từ';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLanguage;

    protected static string|UnitEnum|null $navigationGroup = 'Nội dung';

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'infinitive';

    public static function canAccess(): bool
    {
        return QuyenNoiDung::sua();
    }

    public static function canCreate(): bool
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

    public static function form(Schema $schema): Schema
    {
        $bang = [];

        foreach (self::TEN_BANG as $ma => $ten) {
            $ngoi = $ma === 'imp' ? self::MENH_LENH : self::NGOI;
            $o = [];

            foreach ($ngoi as $i => $nhan) {
                $o[] = TextInput::make("conjugation.{$ma}.{$i}")->label($nhan)->maxLength(80);
            }

            $bang[] = Section::make($ten)->columns(3)->collapsible()->schema($o);
        }

        return $schema->columns(1)->components([
            Section::make('Động từ')->columns(3)->schema([
                TextInput::make('infinitive')->label('Nguyên mẫu')->disabled()->dehydrated(false),
                Select::make('level')->label('Cấp')->options(QuyenNoiDung::CAP)->placeholder('Chưa xếp cấp'),
                QuyenNoiDung::oTrangThai(),
                TextInput::make('meanings.vi')->label('Nghĩa tiếng Việt')->required()->maxLength(255),
                TextInput::make('meanings.en')->label('Nghĩa tiếng Anh')->maxLength(255),
                Select::make('auxiliary')->label('Trợ động từ Perfekt')->options(['haben' => 'haben', 'sein' => 'sein'])->placeholder('—'),
                Toggle::make('is_separable')->label('Động từ tách'),
                TextInput::make('prefix')->label('Tiền tố tách')->maxLength(20),
                Toggle::make('is_irregular')->label('Bất quy tắc'),
                TextInput::make('form_du')->label('du')->maxLength(80),
                TextInput::make('form_er')->label('er/sie/es')->maxLength(80),
                TextInput::make('preterite')->label('Präteritum')->maxLength(80),
                TextInput::make('perfect')->label('Perfekt')->maxLength(120),
            ]),
            ...$bang,
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('infinitive')->label('Động từ')->searchable()->sortable()
                    ->description(fn (Verb $record) => $record->meanings['vi'] ?? null),
                TextColumn::make('level')->label('Cấp')->formatStateUsing(fn (?int $state) => $state !== null ? (QuyenNoiDung::CAP[$state] ?? (string) $state) : null)->placeholder('—'),
                IconColumn::make('is_separable')->label('Tách')->boolean(),
                TextColumn::make('perfect')->label('Perfekt')->placeholder('—'),
                TextColumn::make('status')->label('Trạng thái')->badge()
                    ->formatStateUsing(fn (string $state) => QuyenNoiDung::TRANG_THAI[$state] ?? $state)
                    ->color(fn (string $state) => QuyenNoiDung::mauTrangThai($state)),
            ])
            ->filters([
                SelectFilter::make('level')->label('Cấp')->options(QuyenNoiDung::CAP),
                TernaryFilter::make('is_separable')->label('Động từ tách'),
                SelectFilter::make('status')->label('Trạng thái')->options(QuyenNoiDung::TRANG_THAI),
            ])
            ->defaultSort('infinitive')
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDongTus::route('/'),
            'edit' => EditDongTu::route('/{record}/edit'),
        ];
    }
}
