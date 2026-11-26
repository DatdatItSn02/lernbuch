<?php

namespace App\Models\Concerns;

use App\Support\NhatKy;
use Illuminate\Database\Eloquent\Model;

/**
 * Model có trang quản trị: khi nhân viên tạo, sửa thì ghi người làm vào cột created_by, updated_by (nếu bảng có),
 * và mỗi lần tạo, sửa, xoá ghi một dòng vào nhật ký thao tác, kèm giá trị trước và sau của các cột đã đổi.
 */
trait GhiNhatKy
{
    public static function bootGhiNhatKy(): void
    {
        static::creating(function (Model $model): void {
            $nguoi = NhatKy::nguoiLam();

            if ($nguoi !== null) {
                self::datNeuCo($model, 'created_by', $nguoi->id);
                self::datNeuCo($model, 'updated_by', $nguoi->id);
            }
        });

        static::updating(function (Model $model): void {
            $nguoi = NhatKy::nguoiLam();

            if ($nguoi !== null) {
                self::datNeuCo($model, 'updated_by', $nguoi->id);
            }
        });

        static::created(fn (Model $model) => NhatKy::ghi(NhatKy::viec($model, 'create'), $model, [], $model->getAttributes()));

        static::updated(function (Model $model): void {
            // Chỉ đổi cột không ghi nhật ký (vd băm lại mật khẩu khi đăng xuất máy khác) thì bỏ qua
            $doi = array_diff_key($model->getChanges(), array_flip(NhatKy::BO_QUA));

            if ($doi !== []) {
                NhatKy::ghi(NhatKy::viec($model, 'update'), $model, array_intersect_key($model->getOriginal(), $doi), $doi);
            }
        });

        static::deleted(fn (Model $model) => NhatKy::ghi(NhatKy::viec($model, 'delete'), $model, $model->getAttributes(), []));
    }

    private static function datNeuCo(Model $model, string $cot, int $id): void
    {
        if (in_array($cot, static::cotNguoiSua(), true)) {
            $model->setAttribute($cot, $id);
        }
    }

    /**
     * Cột ghi người làm mà bảng này có (mặc định cả hai); model nào thiếu cột thì khai báo lại.
     *
     * @return list<string>
     */
    public static function cotNguoiSua(): array
    {
        return ['created_by', 'updated_by'];
    }
}
