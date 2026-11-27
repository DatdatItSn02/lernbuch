<?php

namespace App\Filament\Resources\BoBaiTaps\RelationManagers;

use App\Filament\QuyenNoiDung;
use App\Models\Exercise;
use App\Models\ExerciseSet;
use Closure;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Câu của một bộ bài tập: sửa đề, đáp án được chấp nhận, lựa chọn (trắc nghiệm) hay mảnh câu (sắp xếp), gợi ý, lời giải,
 * trạng thái. Máy chủ chấm lại từ đáp án ở đây nên sửa có hiệu lực ngay; sau khi sửa thì đếm lại số câu của bộ.
 */
class CauBaiTapRelationManager extends RelationManager
{
    protected static string $relationship = 'exercises';

    protected static ?string $title = 'Câu';

    protected static ?string $modelLabel = 'câu';

    public function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('sig')->label('Mã câu')->disabled()->dehydrated(false),
            Select::make('kind')->label('Dạng')->options(Exercise::DANG)->disabled()->dehydrated(false),
            Textarea::make('prompt')->label('Đề (chỗ trống là ____)')->required()->rows(2)->maxLength(2000)->columnSpanFull(),
            TagsInput::make('answers')->label('Đáp án được chấp nhận')->required()->columnSpanFull()
                ->rules([fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                    $loi = self::kiemDapAn((string) $get('kind'), is_array($value) ? $value : [], (array) ($get('choices') ?? []));

                    if ($loi !== null) {
                        $fail($loi);
                    }
                }]),
            TagsInput::make('choices')->label('Lựa chọn (trắc nghiệm) hoặc mảnh câu (sắp xếp)')->columnSpanFull(),
            TextInput::make('hint')->label('Gợi ý')->maxLength(500),
            TextInput::make('sort')->label('Thứ tự')->integer()->required()->minValue(-100000)->maxValue(100000),
            Textarea::make('explanation')->label('Lời giải')->rows(2)->maxLength(5000)->columnSpanFull(),
            QuyenNoiDung::oTrangThai(),
        ]);
    }

    /**
     * Trắc nghiệm: mỗi đáp án phải là một lựa chọn (máy chủ chấm bằng cách so đúng lựa chọn). Trả về lời báo lỗi hoặc null.
     *
     * @param  array<int|string, mixed>  $dapAn
     * @param  array<int|string, mixed>  $luaChon
     */
    public static function kiemDapAn(string $dang, array $dapAn, array $luaChon): ?string
    {
        $dapAn = array_values(array_filter(array_map(fn ($v) => trim((string) $v), $dapAn), fn (string $v) => $v !== ''));

        if ($dapAn === []) {
            return 'Cần ít nhất một đáp án.';
        }

        if ($dang === 'mc' && count($luaChon) > 1) {
            $thieu = array_diff($dapAn, array_map(fn ($v) => trim((string) $v), $luaChon));

            if ($thieu !== []) {
                return 'Đáp án trắc nghiệm phải là một trong các lựa chọn: '.implode(', ', $thieu).' không có trong lựa chọn.';
            }
        }

        return null;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sort')->label('#')->sortable(),
                TextColumn::make('prompt')->label('Đề')->limit(80)->searchable(['prompt', 'sig']),
                TextColumn::make('kind')->label('Dạng')->formatStateUsing(fn (string $state) => Exercise::DANG[$state] ?? $state),
                TextColumn::make('answers')->label('Đáp án')->badge(),
                TextColumn::make('status')->label('Trạng thái')->badge()
                    ->formatStateUsing(fn (string $state) => QuyenNoiDung::TRANG_THAI[$state] ?? $state)
                    ->color(fn (string $state) => QuyenNoiDung::mauTrangThai($state)),
            ])
            ->filters([
                SelectFilter::make('status')->label('Trạng thái')->options(QuyenNoiDung::TRANG_THAI),
            ])
            ->defaultSort('sort')
            ->recordActions([
                EditAction::make()->after(fn (Exercise $record) => ExerciseSet::demLaiCau($record->set_id)),
            ]);
    }
}
