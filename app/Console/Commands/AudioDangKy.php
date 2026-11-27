<?php

namespace App\Console\Commands;

use App\Support\AmThanh;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use JsonException;

/**
 * Ghi file audio vừa tạo vào bảng media_assets (workflow audio chạy sau khi chép file lên thư mục audio dùng chung).
 * Đọc manifest.jsonl của tools/audio/tao_audio.py; chạy lại không nhân đôi (khoá kind + hash). Dòng sai thì bỏ, báo số dòng,
 * và trả mã lỗi để workflow thấy; các dòng đúng vẫn được ghi.
 */
class AudioDangKy extends Command
{
    protected $signature = 'app:audio-dang-ky {manifest : Đường dẫn manifest.jsonl}';

    protected $description = 'Ghi các file audio Piper đã tạo (manifest.jsonl) vào media_assets';

    public function handle(): int
    {
        $duong = (string) $this->argument('manifest');

        if (! is_file($duong) || ! is_readable($duong)) {
            $this->components->error("Không đọc được file {$duong}.");

            return self::FAILURE;
        }

        $dong = [];
        $loi = [];
        $now = now()->getTimestamp();

        foreach (file($duong, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $so => $chu) {
            $x = self::doc($chu);

            if (is_string($x)) {
                $loi[] = 'dòng '.($so + 1).': '.$x;

                continue;
            }

            $dong[$x['hash']] = [
                'kind' => AmThanh::LOAI, 'hash' => $x['hash'], 'disk' => AmThanh::O_CHUNG, 'path' => $x['path'], 'mime' => 'audio/mpeg',
                'bytes' => $x['bytes'], 'duration_ms' => $x['duration_ms'], 'voice' => $x['voice'], 'speed' => AmThanh::TOC_DO,
                'text_content' => mb_substr($x['text'], 0, 600), 'created_at' => $now,
            ];
        }

        $moi = count($dong) - ($dong === [] ? 0 : DB::table('media_assets')->where('kind', AmThanh::LOAI)->whereIn('hash', array_keys($dong))->count());

        foreach (array_chunk(array_values($dong), 500) as $lo) {
            DB::table('media_assets')->upsert($lo, ['kind', 'hash'], ['disk', 'path', 'mime', 'bytes', 'duration_ms', 'voice', 'speed', 'text_content']);
        }

        $this->line(sprintf('Đã ghi %d file audio (%d mới), bỏ %d dòng lỗi.', count($dong), $moi, count($loi)));

        foreach (array_slice($loi, 0, 20) as $l) {
            $this->components->warn($l);
        }

        return $loi === [] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Một dòng manifest đã kiểm, hoặc chuỗi lý do sai.
     *
     * @return array{hash: string, path: string, text: string, voice: string, bytes: int|null, duration_ms: int|null}|string
     */
    private static function doc(string $chu): array|string
    {
        try {
            $x = json_decode($chu, true, 4, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return 'không phải JSON';
        }

        if (! is_array($x) || ! is_string($x['hash'] ?? null) || ! is_string($x['voice'] ?? null) || ! is_string($x['text'] ?? null) || ! is_string($x['path'] ?? null)) {
            return 'thiếu hash, voice, text hoặc path';
        }

        if (preg_match(AmThanh::MAU_GIONG, $x['voice']) !== 1) {
            return "giọng '{$x['voice']}' không đúng dạng";
        }

        // Tên file phải đúng luật đặt tên: đúng giọng, đúng hash của chữ, đúng chỗ trong thư mục
        $hash = AmThanh::hash($x['voice'], $x['text']);

        if ($x['hash'] !== $hash || $x['path'] !== AmThanh::duongDan($x['voice'], $hash) || ($x['speed'] ?? AmThanh::TOC_DO) !== AmThanh::TOC_DO) {
            return 'hash hoặc đường dẫn không khớp với giọng và chữ';
        }

        $so = fn (string $k) => isset($x[$k]) && is_int($x[$k]) && $x[$k] >= 0 ? $x[$k] : null;

        return ['hash' => $hash, 'path' => $x['path'], 'text' => AmThanh::chuanHoa($x['text']), 'voice' => $x['voice'], 'bytes' => $so('bytes'), 'duration_ms' => $so('duration_ms')];
    }
}
