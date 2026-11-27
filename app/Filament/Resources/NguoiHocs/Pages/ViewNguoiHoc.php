<?php

namespace App\Filament\Resources\NguoiHocs\Pages;

use App\Filament\Resources\NguoiHocs\NguoiHocResource;
use App\Models\User;
use App\Support\QuanTri\KhoaTaiKhoan;
use App\Support\QuanTri\TienDoNguoiHoc;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Components\Attributes\Unexposed;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;

/** Xem một người học: tài khoản và tiến độ (cùng số liệu với trang Hôm nay của họ); khoá, mở khoá cần users.manage. */
class ViewNguoiHoc extends ViewRecord
{
    protected static string $resource = NguoiHocResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('khoa')
                ->label('Khoá tài khoản')
                ->icon(Heroicon::OutlinedLockClosed)
                ->color('danger')
                ->visible(fn () => NguoiHocResource::duocKhoa() && $this->nguoiHoc()->status === KhoaTaiKhoan::DANG_HOC)
                ->requiresConfirmation()
                ->modalHeading('Khoá tài khoản này?')
                ->modalDescription('Người học bị đăng xuất ngay trên mọi máy và không đăng nhập lại được cho tới khi mở khoá. Tiến độ học giữ nguyên.')
                ->modalSubmitActionLabel('Khoá')
                ->schema([
                    Textarea::make('ly_do')->label('Lý do (ghi vào nhật ký thao tác)')->maxLength(KhoaTaiKhoan::LY_DO_TOI_DA),
                ])
                ->action(function (array $data): void {
                    KhoaTaiKhoan::khoa($this->nguoiHoc(), (string) ($data['ly_do'] ?? ''));
                    Notification::make()->title('Đã khoá tài khoản')->success()->send();
                }),
            Action::make('mo-khoa')
                ->label('Mở khoá')
                ->icon(Heroicon::OutlinedLockOpen)
                ->visible(fn () => NguoiHocResource::duocKhoa() && $this->nguoiHoc()->status === KhoaTaiKhoan::BI_KHOA)
                ->requiresConfirmation()
                ->modalHeading('Mở khoá tài khoản này?')
                ->modalSubmitActionLabel('Mở khoá')
                ->action(function (): void {
                    KhoaTaiKhoan::moKhoa($this->nguoiHoc());
                    Notification::make()->title('Đã mở khoá tài khoản')->success()->send();
                }),
        ];
    }

    #[Unexposed]
    public function infolist(Schema $schema): Schema
    {
        $user = $this->nguoiHoc();
        $t = TienDoNguoiHoc::cua($user);
        $so = fn (int $n) => number_format($n, 0, ',', '.');
        $ngay = array_map(
            fn (array $d) => substr($d['date'], 8, 2).'/'.substr($d['date'], 5, 2).': '.$so($d['count']),
            $t['ngay'],
        );

        return $schema->columns(1)->components([
            Section::make('Tài khoản')->columns(3)->schema([
                TextEntry::make('name')->label('Tên'),
                TextEntry::make('email')->label('Email')->copyable(),
                TextEntry::make('status')->label('Trạng thái')->badge()
                    ->formatStateUsing(fn (string $state) => NguoiHocResource::TRANG_THAI[$state] ?? $state)
                    ->color(fn (string $state) => $state === KhoaTaiKhoan::DANG_HOC ? 'success' : 'danger'),
                TextEntry::make('created_at')->label('Ngày đăng ký')->dateTime('d/m/Y H:i'),
                TextEntry::make('email_verified_at')->label('Xác minh email')->dateTime('d/m/Y H:i')->placeholder('Chưa xác minh'),
                TextEntry::make('last_login_at')->label('Đăng nhập lần cuối')->dateTime('d/m/Y H:i')->placeholder('Chưa đăng nhập'),
                TextEntry::make('may')->label('Máy đang đăng nhập')->state($so($t['may'])),
                TextEntry::make('timezone')->label('Múi giờ'),
                TextEntry::make('public_id')->label('Mã người học'),
            ]),
            Section::make('Tiến độ học')->columns(3)->schema([
                TextEntry::make('da_hoc')->label('Thẻ đã học')->state($so($t['da_hoc'])),
                TextEntry::make('due')->label('Đến hạn hôm nay')->state($so($t['due'])),
                TextEntry::make('hom_nay')->label('Đã ôn hôm nay')->state($so($t['reviewed_today']).' / '.$so($t['goal']).' thẻ'),
                TextEntry::make('theo_muc')->label('Thẻ theo mức')->state(TienDoNguoiHoc::theoMuc($t['levels']))->columnSpanFull(),
                TextEntry::make('chuoi')->label('Chuỗi ngày')->state($so($t['streak']).' ngày (dài nhất '.$so($t['streak_longest']).')'),
                TextEntry::make('xu')->label('Xu')->state($so($t['coins']).' (hôm nay '.$so($t['coins_today']).')'),
                TextEntry::make('dang_hoc')->label('Chủ đề đang học')->state($t['dang_hoc'])->placeholder('Chưa học'),
                TextEntry::make('phien')->label('Phiên học')->state($so($t['phien'])),
                TextEntry::make('phien_cuoi')->label('Học lần cuối')
                    ->state($t['phien_cuoi'] !== null ? Carbon::createFromTimestamp($t['phien_cuoi']) : null)
                    ->dateTime('d/m/Y H:i')->placeholder('Chưa học'),
                TextEntry::make('bai_tap')->label('Lượt làm bài tập')->state($so($t['bai_tap'])),
                TextEntry::make('muc_tieu')->label('Mục tiêu')->state($t['muc_tieu'])->placeholder('Chưa chọn'),
                TextEntry::make('trinh_do')->label('Trình độ tự chọn')->state($t['trinh_do'])->placeholder('Chưa chọn'),
                TextEntry::make('ngay')->label('Số thẻ ôn 10 ngày qua và hôm nay')->state(implode(' · ', $ngay))->columnSpanFull(),
            ]),
        ]);
    }

    private function nguoiHoc(): User
    {
        $user = $this->getRecord();
        assert($user instanceof User);

        return $user;
    }
}
