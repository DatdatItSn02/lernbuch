<?php

// Chấm nhanh hơn ngưỡng của chế độ thì vẫn ghi tiến độ nhưng không cộng xu: lý do ghi vào nhật ký lượt chấm.
// schema.sql đã sửa theo.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('review_logs', function (Blueprint $table) {
            $table->string('no_coin_reason', 20)->charset('ascii')->collation('ascii_bin')->nullable()->after('coins')
                ->comment('lý do không cộng xu: too_fast (chấm nhanh hơn ngưỡng của chế độ)');
        });
    }

    public function down(): void
    {
        Schema::table('review_logs', function (Blueprint $table) {
            $table->dropColumn('no_coin_reason');
        });
    }
};
