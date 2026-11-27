<?php

namespace App\Filament\Resources\NhatKys;

use App\Filament\Resources\NhatKys\Pages\ListNhatKys;
use App\Filament\Resources\NhatKys\Pages\ViewNhatKy;
use App\Models\AuditLog;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\KeyValueEntry;
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

/** Nhật ký thao tác của nhân viên (chỉ xem): ai, lúc nào, làm gì, trên dòng nào, giá trị trước và sau. Không sửa, không xoá. */
class NhatKyResource extends Resource
{
    /** Tên dễ đọc của các việc hay gặp; việc khác hiện mã. */
    public const VIEC = [
        'user.create' => 'Tạo tài khoản',
        'user.update' => 'Sửa tài khoản',
        'user.roles' => 'Đổi vai trò',
        'user.suspend' => 'Khoá tài khoản',
        'user.unsuspend' => 'Mở khoá tài khoản',
        'role.permissions' => 'Đổi quyền của vai trò',
        'card.update' => 'Sửa thẻ',
        'card.meanings' => 'Sửa nghĩa của thẻ',
        'card.examples' => 'Sửa câu ví dụ',
        'topic.update' => 'Sửa chủ đề',
        'grammar_lesson.update' => 'Sửa bài ngữ pháp',
        'exercise_set.update' => 'Sửa bộ bài tập',
        'exercise.update' => 'Sửa câu bài tập',
        'verb.update' => 'Sửa động từ',
        'content_source.create' => 'Thêm nguồn',
        'content_source.update' => 'Sửa nguồn',
        'failed_job.retry' => 'Chạy lại job lỗi',
        'failed_job.forget' => 'Bỏ job lỗi',
        'setting.update' => 'Sửa cài đặt',
    ];

    protected static ?string $model = AuditLog::class;

    protected static ?string $slug = 'nhat-ky';

    protected static ?string $modelLabel = 'dòng nhật ký';

    protected static ?string $pluralModelLabel = 'Nhật ký thao tác';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|UnitEnum|null $navigationGroup = 'Hệ thống';

    protected static ?int $navigationSort = 40;

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

    public static function tenViec(string $viec): string
    {
        return self::VIEC[$viec] ?? $viec;
    }

    /**
     * Giá trị trước hoặc sau, dạng chữ cho bảng khoá–giá trị (mảng hiện bằng JSON).
     *
     * @return array<string, string>
     */
    public static function dangChu(mixed $giaTri): array
    {
        if (! is_array($giaTri)) {
            return [];
        }

        $ra = [];

        foreach ($giaTri as $khoa => $v) {
            $ra[(string) $khoa] = is_string($v) ? $v : (string) json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return $ra;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextEntry::make('created_at')->label('Lúc')->dateTime('d/m/Y H:i:s'),
            TextEntry::make('actor.name')->label('Người làm')->placeholder('Hệ thống'),
            TextEntry::make('action')->label('Việc')->formatStateUsing(fn (string $state) => self::tenViec($state).' ('.$state.')'),
            TextEntry::make('doi_tuong')->label('Trên dòng')
                ->state(fn (AuditLog $record) => $record->subject_type !== null ? class_basename($record->subject_type).' #'.$record->subject_id : null)
                ->placeholder('—'),
            TextEntry::make('ip_chu')->label('IP')->state(fn (AuditLog $record) => $record->ipChu())->placeholder('—'),
            TextEntry::make('user_agent')->label('Trình duyệt')->placeholder('—'),
            KeyValueEntry::make('truoc')->label('Trước')->keyLabel('Cột')->valueLabel('Giá trị')
                ->state(fn (AuditLog $record) => self::dangChu($record->changes['before'] ?? null))->columnSpanFull(),
            KeyValueEntry::make('sau')->label('Sau')->keyLabel('Cột')->valueLabel('Giá trị')
                ->state(fn (AuditLog $record) => self::dangChu($record->changes['after'] ?? null))->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('actor'))
            ->columns([
                TextColumn::make('created_at')->label('Lúc')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('actor.name')->label('Người làm')->placeholder('Hệ thống'),
                TextColumn::make('action')->label('Việc')->formatStateUsing(fn (string $state) => self::tenViec($state))->searchable(),
                TextColumn::make('subject_type')->label('Trên dòng')
                    ->formatStateUsing(fn (string $state, AuditLog $record) => class_basename($state).' #'.$record->subject_id)
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('action')->label('Việc')
                    ->options(fn () => AuditLog::query()->distinct()->orderBy('action')->pluck('action', 'action')->map(fn (string $v) => self::tenViec($v))->all()),
                SelectFilter::make('actor_id')->label('Người làm')->relationship('actor', 'name'),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([ViewAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNhatKys::route('/'),
            'view' => ViewNhatKy::route('/{record}'),
        ];
    }
}
