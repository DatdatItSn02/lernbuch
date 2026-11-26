<?php

namespace App\Support\NhapLernbuch;

/** Báo cáo của một lần nhập (chạy thử hoặc ghi): số dòng theo từng phần, phần bị bỏ vì nguồn chưa có quyền, lỗi dữ liệu. */
final class BaoCaoNhap
{
    /** @var array<string, int> thẻ theo loại (vocab, phrase…) */
    public array $theoLoai = [];

    /** @var array<string, int> thẻ theo nguồn (mã content_sources) */
    public array $theoNguon = [];

    /** @var array<string, int> thẻ theo cấp (A1…, chua_xep) */
    public array $theoCap = [];

    /** @var array<string, int> dòng bị bỏ vì nguồn chưa có quyền dùng để bán, theo mã nguồn */
    public array $boViNguon = [];

    /** @var array<string, int> số dòng sẽ nhập/đã nhập: the, chu_de_nhom, chu_de_vua, chu_de_nho, bai_ngu_phap, chuong_ngu_phap, bo_bai_tap, cau_bai_tap, dong_tu */
    public array $so = [];

    /** @var array<string, array{moi: int, cap_nhat: int, xoa: int}> kết quả ghi theo phần */
    public array $daGhi = [];

    /** @var list<string> lỗi dữ liệu: có lỗi thì không ghi gì */
    public array $loi = [];

    /** @var list<string> cảnh báo: vẫn ghi được */
    public array $canhBao = [];

    public int $theTat = 0;

    public bool $daGhiVao = false;

    public function __construct(public readonly string $nguon, public readonly ?string $phienBan) {}

    public function coLoi(): bool
    {
        return $this->loi !== [];
    }

    public function dem(string $phan, int $them = 1): void
    {
        $this->so[$phan] = ($this->so[$phan] ?? 0) + $them;
    }

    public function loi(string $chu): void
    {
        $this->loi[] = $chu;
    }

    public function ghiNhan(string $phan, int $moi, int $capNhat, int $xoa = 0): void
    {
        $cu = $this->daGhi[$phan] ?? ['moi' => 0, 'cap_nhat' => 0, 'xoa' => 0];
        $this->daGhi[$phan] = ['moi' => $cu['moi'] + $moi, 'cap_nhat' => $cu['cap_nhat'] + $capNhat, 'xoa' => $cu['xoa'] + $xoa];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'nguon' => $this->nguon,
            'phien_ban' => $this->phienBan,
            'da_ghi' => $this->daGhiVao,
            'so' => $this->so,
            'the_theo_loai' => $this->theoLoai,
            'the_theo_nguon' => $this->theoNguon,
            'the_theo_cap' => $this->theoCap,
            'the_da_tat' => $this->theTat,
            'bo_vi_nguon' => $this->boViNguon,
            'ket_qua_ghi' => $this->daGhi,
            'loi' => $this->loi,
            'canh_bao' => $this->canhBao,
        ];
    }
}
