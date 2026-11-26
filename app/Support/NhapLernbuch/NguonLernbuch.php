<?php

namespace App\Support\NhapLernbuch;

use Generator;
use PDO;
use PDOStatement;
use RuntimeException;
use ZipArchive;

/**
 * Dữ liệu của Lernbuch cũ: database SQLite (content/seed.db, hoặc data/app.db của máy đã chạy app cũ)
 * và cây chủ đề content/de/topics_fine.json. Nhận lernweb.zip, thư mục lernweb (hoặc content), hoặc thẳng file .db.
 * Chỉ đọc, không sửa gì ở nguồn.
 */
final class NguonLernbuch
{
    private PDO $pdo;

    private string $fileDb;

    private string $fileChuDe;

    private ?string $thuMucTam = null;

    public function __construct(private readonly string $duongDan)
    {
        if (! extension_loaded('pdo_sqlite')) {
            throw new RuntimeException('PHP chưa bật pdo_sqlite (cần để đọc database SQLite của Lernbuch cũ).');
        }

        [$this->fileDb, $this->fileChuDe] = $this->timFile($duongDan);
        $this->pdo = new PDO('sqlite:'.$this->fileDb, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::SQLITE_ATTR_OPEN_FLAGS => PDO::SQLITE_OPEN_READONLY,
        ]);

        foreach (['cards', 'docs', 'ex_sets', 'exercises', 'verbs'] as $bang) {
            if (! $this->coBang($bang)) {
                throw new RuntimeException("{$this->fileDb} không phải database của Lernbuch: thiếu bảng {$bang}.");
            }
        }
    }

    public function __destruct()
    {
        if ($this->thuMucTam !== null) {
            foreach (glob($this->thuMucTam.'/*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($this->thuMucTam);
        }
    }

    public function moTa(): string
    {
        return $this->duongDan;
    }

    /** Phiên bản dữ liệu ghi trong kv (vd x19), không có thì null. */
    public function phienBan(): ?string
    {
        if (! $this->coBang('kv')) {
            return null;
        }

        $v = $this->cau("SELECT val FROM kv WHERE key = 'extra_de'")->fetchColumn();

        return is_string($v) ? $v : null;
    }

    /**
     * Cây chủ đề: danh sách chủ đề (code, vi, en, emoji, pcode, pvi "Nhóm › Chủ đề vừa", gid, sort)
     * và bảng đổi chủ đề gốc của nguồn sang mã chủ đề (theme_map).
     *
     * @return array{topics: list<array<string, mixed>>, theme_map: array<string, string>}
     */
    public function cayChuDe(): array
    {
        $d = json_decode((string) file_get_contents($this->fileChuDe), true);

        if (! is_array($d) || ! isset($d['topics']) || ! is_array($d['topics'])) {
            throw new RuntimeException("{$this->fileChuDe} không đúng dạng (thiếu topics).");
        }

        /** @var list<array<string, mixed>> $topics */
        $topics = array_values(array_filter($d['topics'], 'is_array'));
        /** @var array<string, string> $map */
        $map = is_array($d['theme_map'] ?? null) ? array_filter($d['theme_map'], 'is_string') : [];

        return ['topics' => $topics, 'theme_map' => $map];
    }

    /**
     * Đọc từng dòng của một câu SELECT, theo lô để vừa bộ nhớ nhỏ (256 MB trên alwaysdata).
     *
     * @param  array<int, mixed>  $thamSo
     * @return Generator<int, array<string, mixed>>
     */
    public function dong(string $sql, array $thamSo = [], int $lo = 1000): Generator
    {
        for ($tu = 0; ; $tu += $lo) {
            $dong = $this->cau($sql." LIMIT {$lo} OFFSET {$tu}", $thamSo)->fetchAll();

            foreach ($dong as $d) {
                yield $d;
            }

            if (count($dong) < $lo) {
                return;
            }
        }
    }

    /** @return array<string, mixed>|null */
    public function mot(string $sql): ?array
    {
        $d = $this->cau($sql)->fetch();

        return is_array($d) ? $d : null;
    }

    private function coBang(string $ten): bool
    {
        return $this->cau("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = ?", [$ten])->fetchColumn() !== false;
    }

    /** @param  array<int, mixed>  $thamSo */
    private function cau(string $sql, array $thamSo = []): PDOStatement
    {
        $st = $this->pdo->prepare($sql);

        if ($st === false) {
            throw new RuntimeException("Không đọc được database của Lernbuch: {$sql}");
        }

        $st->execute($thamSo);

        return $st;
    }

    /** @return array{string, string} file database và file cây chủ đề */
    private function timFile(string $duongDan): array
    {
        if (is_file($duongDan) && strtolower(pathinfo($duongDan, PATHINFO_EXTENSION)) === 'zip') {
            return $this->giaiNen($duongDan);
        }

        if (is_file($duongDan)) {
            $db = $duongDan;
            $goc = dirname($duongDan);
            $chuDe = $this->dauTienCo([$goc.'/de/topics_fine.json', $goc.'/content/de/topics_fine.json', dirname($goc).'/content/de/topics_fine.json']);
        } elseif (is_dir($duongDan)) {
            $db = $this->dauTienCo([$duongDan.'/content/seed.db', $duongDan.'/seed.db', $duongDan.'/data/app.db']);
            $chuDe = $this->dauTienCo([$duongDan.'/content/de/topics_fine.json', $duongDan.'/de/topics_fine.json']);
        } else {
            throw new RuntimeException("Không thấy {$duongDan}.");
        }

        if ($db === null) {
            throw new RuntimeException("Không thấy database của Lernbuch (content/seed.db hoặc data/app.db) trong {$duongDan}.");
        }

        if ($chuDe === null) {
            throw new RuntimeException("Không thấy cây chủ đề content/de/topics_fine.json cạnh {$db}.");
        }

        return [$db, $chuDe];
    }

    /** @return array{string, string} */
    private function giaiNen(string $zip): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('PHP chưa bật zip: giải nén lernweb.zip trước rồi chỉ vào thư mục lernweb.');
        }

        $z = new ZipArchive;

        if ($z->open($zip) !== true) {
            throw new RuntimeException("Không mở được {$zip}.");
        }

        $tim = ['seed.db' => 'content/seed.db', 'topics_fine.json' => 'content/de/topics_fine.json'];
        $this->thuMucTam = sys_get_temp_dir().'/nhap-lernbuch-'.bin2hex(random_bytes(6));
        mkdir($this->thuMucTam, 0700, true);
        $ra = [];

        for ($i = 0; $i < $z->numFiles; $i++) {
            $ten = (string) $z->getNameIndex($i);

            foreach ($tim as $file => $duoi) {
                if (str_ends_with($ten, $duoi) && ! isset($ra[$file])) {
                    $dich = $this->thuMucTam.'/'.$file;
                    copy('zip://'.$zip.'#'.$ten, $dich);
                    $ra[$file] = $dich;
                }
            }
        }
        $z->close();

        if (! isset($ra['seed.db'], $ra['topics_fine.json'])) {
            throw new RuntimeException("{$zip} thiếu content/seed.db hoặc content/de/topics_fine.json.");
        }

        return [$ra['seed.db'], $ra['topics_fine.json']];
    }

    /** @param  list<string>  $ds */
    private function dauTienCo(array $ds): ?string
    {
        foreach ($ds as $f) {
            if (is_file($f)) {
                return $f;
            }
        }

        return null;
    }
}
