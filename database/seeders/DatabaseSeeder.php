<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Nạp database/sql/seed.sql từng câu một (lỗi ở câu nào thì dừng và báo câu đó).
     * Chạy lại nhiều lần không nhân đôi vì mỗi INSERT có ON DUPLICATE KEY UPDATE.
     */
    public function run(): void
    {
        $sql = (string) file_get_contents(database_path('sql/seed.sql'));

        foreach (preg_split('/;[ \t]*\R/', $sql) ?: [] as $statement) {
            $code = trim((string) preg_replace('/^\s*--.*$/m', '', $statement));

            if ($code !== '') {
                DB::unprepared($code);
            }
        }
    }
}
