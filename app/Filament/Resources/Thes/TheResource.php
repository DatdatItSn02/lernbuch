<?php

namespace App\Filament\Resources\Thes;

use App\Filament\QuyenNoiDung;
use App\Filament\Resources\Thes\Pages\EditThe;
use App\Filament\Resources\Thes\Pages\ListThes;
use App\Models\Card;
use App\Models\CardExample;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * Thẻ học của hệ thống (thẻ riêng của người học không hiện ở đây): sửa mặt trước, dạng, cấp, nghĩa 3 thứ tiếng, câu ví dụ,
 * câu Điền câu, nguồn. Trạng thái cần quyền xuất bản. Đổi mặt trước thì audio cũ không khớp nữa: app đọc bằng giọng trình duyệt
 * tới khi chạy lại workflow audio (chỉ tạo phần thiếu).
 */
class TheResource extends Resource
{
    protected static ?string $model = Card::class;

    protected static ?string $slug = 'the';

    protected static ?string $modelLabel = 'thẻ';

    protected static ?string $pluralModelLabel = 'Thẻ';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Nội dung';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'front';

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

    /** Chỉ thẻ của hệ thống: thẻ riêng của người học là dữ liệu của họ, nhân viên không sửa. */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereNull('owner_user_id');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Thẻ')->columns(3)->schema([
                TextInput::make('front')->label('Mặt trước')->required()->maxLength(500)->columnSpan(2),
                TextInput::make('card_key')->label('Khoá thẻ')->disabled()->dehydrated(false),
                Select::make('article')->label('Mạo từ')->options(['der' => 'der', 'die' => 'die', 'das' => 'das'])->placeholder('Không có'),
                Select::make('gender')->label('Màu giống')->options(Card::GIONG)->placeholder('Không tô màu'),
                TextInput::make('pos')->label('Loại từ')->maxLength(16)->datalist(['noun', 'verb', 'adj', 'adv', 'prep', 'pron', 'conj', 'num', 'phrase', 'sentence', 'other']),
                TextInput::make('forms')->label('Dạng (số nhiều, dạng chia)')->maxLength(255)->columnSpan(2),
                Select::make('source_id')->label('Nguồn')->relationship('source', 'name')->searchable()->preload(),
                Select::make('level')->label('Cấp')->options(QuyenNoiDung::CAP)->placeholder('Chưa xếp cấp'),
                Select::make('level_min')->label('Cấp thấp nhất')->options(QuyenNoiDung::CAP)->placeholder('—'),
                Select::make('level_max')->label('Cấp cao nhất')->options(QuyenNoiDung::CAP)->placeholder('—'),
                QuyenNoiDung::oTrangThai(),
            ]),
            Section::make('Nghĩa')->columns(3)->schema([
                TextInput::make('nghia_vi')->label('Tiếng Việt')->required()->maxLength(500),
                TextInput::make('nghia_en')->label('Tiếng Anh')->maxLength(500),
                TextInput::make('nghia_zh')->label('Tiếng Trung')->maxLength(500),
            ]),
            Section::make('Câu ví dụ')->description('Câu đầu tiên là câu chính (Đặt câu và Hỗn hợp dùng câu này). Tối đa '.CardExample::TOI_DA.' câu.')->schema([
                Repeater::make('cau_vi_du')->hiddenLabel()
                    ->schema([
                        TextInput::make('sentence')->label('Câu tiếng Đức')->required()->maxLength(600)->columnSpanFull(),
                        TextInput::make('vi')->label('Dịch tiếng Việt')->maxLength(600),
                        TextInput::make('en')->label('Dịch tiếng Anh')->maxLength(600),
                    ])
                    ->columns(2)
                    ->maxItems(CardExample::TOI_DA)
                    ->defaultItems(0)
                    ->reorderable()
                    ->addActionLabel('Thêm câu ví dụ'),
            ]),
            Section::make('Điền câu và ghi chú')->columns(2)->schema([
                TextInput::make('cloze')->label('Câu Điền câu (chỗ trống là ____)')->maxLength(500)->regex('/_{4}/'),
                TextInput::make('cloze_answer')->label('Đáp án Điền câu')->maxLength(120)->requiredWith('cloze'),
                Textarea::make('tip')->label('Mẹo')->rows(2)->maxLength(2000),
                Textarea::make('note')->label('Ghi chú')->rows(2)->maxLength(2000),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['topic', 'source']))
            ->columns([
                TextColumn::make('front')->label('Mặt trước')->sortable()
                    ->searchable(query: fn (Builder $query, string $search) => self::tim($query, $search))
                    ->description(fn (Card $record) => $record->forms),
                TextColumn::make('topic.name_vi')->label('Chủ đề')->placeholder('—'),
                TextColumn::make('collection')->label('Loại thẻ'),
                TextColumn::make('level')->label('Cấp')->formatStateUsing(fn (?int $state) => $state !== null ? (QuyenNoiDung::CAP[$state] ?? (string) $state) : null)->placeholder('—'),
                TextColumn::make('status')->label('Trạng thái')->badge()
                    ->formatStateUsing(fn (string $state) => QuyenNoiDung::TRANG_THAI[$state] ?? $state)
                    ->color(fn (string $state) => QuyenNoiDung::mauTrangThai($state)),
                TextColumn::make('source.code')->label('Nguồn')->placeholder('—')->toggleable(),
                TextColumn::make('updated_at')->label('Sửa lần cuối')->dateTime('d/m/Y H:i')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('collection')->label('Loại thẻ')->options(fn () => Card::query()->whereNull('owner_user_id')->distinct()->orderBy('collection')->pluck('collection', 'collection')->all()),
                SelectFilter::make('status')->label('Trạng thái')->options(QuyenNoiDung::TRANG_THAI),
                SelectFilter::make('level')->label('Cấp')->options(QuyenNoiDung::CAP),
                SelectFilter::make('topic_id')->label('Chủ đề')->relationship('topic', 'name_vi')->searchable(),
                SelectFilter::make('source_id')->label('Nguồn')->relationship('source', 'name'),
            ])
            ->searchPlaceholder('Tìm từ hoặc nghĩa')
            ->defaultSort('id')
            ->recordActions([EditAction::make()]);
    }

    /** Tìm như Tra từ: mặt trước, hoặc chữ để tìm (dạng, nghĩa mọi thứ tiếng, không dấu). */
    public static function tim(Builder $query, string $search): Builder
    {
        return $query->where(function (Builder $q) use ($search): void {
            $q->where('front', 'like', '%'.$search.'%')
                ->orWhere('search_text', 'like', '%'.mb_strtolower(Str::ascii($search)).'%');
        });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListThes::route('/'),
            'edit' => EditThe::route('/{record}/edit'),
        ];
    }
}
