<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

/**
 * Tài khoản người học và nhân viên. Thời gian lưu dạng Unix timestamp (cột INT).
 *
 * @property int $id
 * @property string $public_id
 * @property string $email
 * @property string $name
 * @property string $status
 * @property bool $is_staff
 */
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    protected $dateFormat = 'U';

    protected $fillable = ['name', 'email', 'password', 'ui_locale', 'timezone', 'birth_date'];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'last_active_at' => 'datetime',
            'deletion_requested_at' => 'datetime',
            'password' => 'hashed',
            'is_staff' => 'boolean',
            'birth_date' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            if (empty($user->public_id)) {
                $user->public_id = (string) Str::ulid();
            }
        });
    }

    /** Chỉ nhân viên đang hoạt động vào được trang quản trị. */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_staff && $this->status === 'active';
    }
}
