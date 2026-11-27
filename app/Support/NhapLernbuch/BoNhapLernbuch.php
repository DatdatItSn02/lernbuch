<?php

namespace App\Support\NhapLernbuch;

use App\Models\ContentSource;
use App\Support\NoiDung;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Nhập nội dung Lernbuch cũ (thẻ, chủ đề, ngữ pháp, bài tập, động từ) vào database.
 *
 * - Chạy thử (mặc định): đọc, kiểm quy tắc dữ liệu, đếm; không ghi gì.
 * - Ghi: chỉ khi không có lỗi dữ liệu nào. Chỉ nhập phần thuộc nguồn có quyền dùng để bán (content_sources.commercial_use);
 *   phần của nguồn khác chỉ được đếm, và nếu lần trước đã nhập thì bị xoá.
 * - Giữ khoá ổn định của Lernbuch (khoá thẻ, mã chủ đề, slug bài, sig câu bài tập): chạy lại không nhân đôi, giữ id nên giữ tiến độ.
 * - Đọc và ghi theo lô để vừa bộ nhớ nhỏ.
 */
final class BoNhapLernbuch
{
    public const LANG = 'de';

    private const LO = 500;

    /** Cột src của thẻ cũ → mã nguồn trong content_sources. Thẻ không ghi src là của file Lernbuch xlsx. */
    private const NGUON_THE = [
        '' => 'lernbuch_xlsx',
        'tr' => 'vocab_trainer',
        'ct' => 'cum_tu_mau_cau',
        'Collins German 3000 Words and Phrases' => 'collins_3000',
        'English for Everyday Activities' => 'english_everyday',
        'German Slang – Sổ tay tiếng Đức' => 'german_slang',
        'bổ sung từ câu ví dụ' => 'own',
    ];

    /** Tiền tố khoá bộ bài tập → nguồn. */
    private const NGUON_BO_BAI_TAP = ['dx' => 'grammar_doc', 'bt' => 'lernbuch_xlsx', 'pl' => 'illustrated_dict', 'pr' => 'grammar_outline'];

    private const LOAI_TU = [
        'Noun' => 'noun', 'Verb' => 'verb', 'Phrase' => 'phrase', 'Other' => 'other', 'Adj.' => 'adj', 'Adj' => 'adj',
        'Adj./Adv.' => 'adj_adv', 'Adv.' => 'adv', 'Sep. verb' => 'sepverb', 'Động từ tách' => 'sepverb', 'Slang' => 'slang',
        'Sentence' => 'sentence', 'Mẫu câu' => 'pattern', 'Pattern' => 'pattern', 'Q & A' => 'qa', 'Expr' => 'expr',
    ];

    /** Mã chủ đề nhỏ = mã chủ đề cũ + đuôi theo loại thẻ (một chủ đề cũ có thẻ của nhiều loại). */
    private const DUOI_LOAI = ['vocab' => 'v', 'phrase' => 'p', 'comm' => 'c', 'pattern' => 'm', 'verbprep' => 'g', 'sepverb' => 't', 'name' => 'n'];

    private const SLUG_LOAI = ['vocab' => 'tu-vung', 'phrase' => 'cum-cau', 'comm' => 'giao-tiep', 'pattern' => 'mau-cau', 'verbprep' => 'dong-tu-gioi-tu', 'sepverb' => 'dong-tu-tach', 'name' => 'ten-rieng'];

    private const CAP = ['A1' => 1, 'A2' => 2, 'B1' => 3, 'B2' => 4, 'C1' => 5, 'C2' => 6];

    private const GIONG = ['der' => 'm', 'die' => 'f', 'das' => 'n'];

    /** Số câu ví dụ tối đa mỗi thẻ (như ghi chú của bảng card_examples). */
    private const VI_DU_TOI_DA = 4;

    private const KIEU_BAI_TAP = ['fill', 'mc', 'order', 'fix'];

    /** Độ dài tối đa của sig câu bài tập (cột exercises.sig). Sig giữ nguyên văn, kể cả chữ Đức như "pl:e:Föhn". */
    private const SIG_TOI_DA = 64;

    private BaoCaoNhap $bc;

    /** @var array<string, string> mã nguồn => commercial_use */
    private array $quyen = [];

    /** @var array<string, int> mã nguồn => id */
    private array $nguonId = [];

    /** @var array<string, bool> loại thẻ => chỉ nhận từ đơn */
    private array $loaiThe = [];

    /** @var array<string, array<string, mixed>> chủ đề cũ theo mã */
    private array $chuDe = [];

    /** @var array<string, string> chủ đề gốc của nguồn → mã chủ đề */
    private array $doiChuDe = [];

    /** @var array<string, int> "loại|mã chủ đề" → số thẻ được nhập */
    private array $chuDeNho = [];

    private int $bay = 0;

    public function __construct(private readonly NguonLernbuch $nguon) {}

    public function chay(bool $ghi): BaoCaoNhap
    {
        $this->bc = new BaoCaoNhap($this->nguon->moTa(), $this->nguon->phienBan());
        $this->bay = time();
        $this->napDanhMuc();

        if (! $this->bc->coLoi()) {
            $this->kiemThe();
            $this->kiemPhanKhac();
        }

        if ($this->bc->coLoi() || ! $ghi) {
            return $this->bc;
        }

        $chuDeId = $this->ghiChuDe();
        $this->ghiThe($chuDeId);
        $baiId = $this->ghiNguPhap();
        $this->ghiBaiTap($baiId);
        $this->ghiDongTu();
        $this->bc->daGhiVao = true;

        return $this->bc;
    }

    /** Từ vựng chỉ nhận từ đơn: bỏ ghi chú trong ngoặc, mạo từ, "sich"; các cách viết ngăn bằng "/" phải đều là một từ. */
    public static function laTuDon(string $front): bool
    {
        foreach (self::cacCachViet($front) as $phan) {
            if (preg_match('/\s/u', $phan) === 1) {
                return false;
            }
        }

        return true;
    }

    /** @return list<string> các cách viết, đã bỏ ngoặc, mạo từ, "sich" */
    private static function cacCachViet(string $front): array
    {
        $s = preg_replace('/\([^)]*\)/u', '', $front) ?? $front;
        $ra = [];

        foreach (preg_split('/\s*\/\s*/u', $s) ?: [] as $phan) {
            $phan = trim((string) preg_replace('/^(der|die|das|ein|eine)\s+/iu', '', trim($phan)));
            $phan = trim((string) preg_replace('/^sich\s+|\s+sich$/iu', '', $phan));

            if ($phan !== '' && ! in_array(mb_strtolower($phan), ['der', 'die', 'das'], true)) {
                $ra[] = $phan;
            }
        }

        return $ra;
    }

    private function napDanhMuc(): void
    {
        /** @var array<string, string> $quyen */
        $quyen = DB::table('content_sources')->whereNull('deleted_at')->pluck('commercial_use', 'code')->all();
        $this->quyen = $quyen;
        /** @var array<string, int> $nguonId */
        $nguonId = DB::table('content_sources')->whereNull('deleted_at')->pluck('id', 'code')->map(fn ($v) => (int) $v)->all();
        $this->nguonId = $nguonId;
        /** @var array<string, bool> $loaiThe */
        $loaiThe = DB::table('card_collections')->pluck('single_word_only', 'code')->map(fn ($v) => (bool) $v)->all();
        $this->loaiThe = $loaiThe;

        if ($this->loaiThe === []) {
            $this->bc->loi('Chưa có danh sách loại thẻ (card_collections): chạy php artisan db:seed trước.');
        }

        $canCo = [...array_values(self::NGUON_THE), ...array_values(self::NGUON_BO_BAI_TAP), 'grammar_outline', 'german_slang'];

        foreach (array_unique($canCo) as $ma) {
            if (! isset($this->quyen[$ma])) {
                $this->bc->loi("Thiếu nguồn {$ma} trong content_sources: chạy php artisan db:seed trước.");
            }
        }

        $cay = $this->nguon->cayChuDe();

        foreach ($cay['topics'] as $t) {
            $ma = (string) ($t['code'] ?? '');

            if ($ma === '' || ! str_contains((string) ($t['pvi'] ?? ''), ' › ')) {
                $this->bc->loi('Cây chủ đề có chủ đề thiếu mã hoặc thiếu nhóm (pvi "Nhóm › Chủ đề vừa"): '.json_encode($t, JSON_UNESCAPED_UNICODE));

                continue;
            }

            // Quy tắc Lernbuch: không có chủ đề chung kiểu "Common Verbs"
            if (preg_match('/^(common|general|misc|other)\b/i', (string) ($t['en'] ?? '')) === 1 || preg_match('/^(chung|khác)\b/iu', (string) ($t['vi'] ?? '')) === 1) {
                $this->bc->loi("Chủ đề {$ma} \"{$t['vi']}\" là chủ đề chung: mỗi thẻ phải thuộc một chủ đề cụ thể.");
            }

            $this->chuDe[$ma] = $t;
        }

        $this->doiChuDe = $cay['theme_map'];
    }

    private function duocBan(string $maNguon): bool
    {
        return in_array($this->quyen[$maNguon] ?? 'unknown', ContentSource::DUOC_BAN, true);
    }

    private function boViNguon(string $maNguon, int $so = 1): void
    {
        $this->bc->boViNguon[$maNguon] = ($this->bc->boViNguon[$maNguon] ?? 0) + $so;
    }

    // ------------------------------------------------------------------ kiểm và đếm

    private function kiemThe(): void
    {
        $tat = $this->nguon->mot("SELECT COUNT(*) AS n FROM cards WHERE active = 0 AND lang = 'de'");
        $this->bc->theTat = (int) ($tat['n'] ?? 0);

        foreach ($this->nguon->dong("SELECT id, collection, key, front, src, theme, topic, level FROM cards WHERE active = 1 AND lang = 'de' ORDER BY id") as $r) {
            $the = $this->kiemMotThe($r);

            if ($the === null) {
                continue;
            }

            [$maNguon, $loai, $chuDe, $cap] = $the;

            if (! $this->duocBan($maNguon)) {
                $this->boViNguon($maNguon);

                continue;
            }

            $this->bc->theoLoai[$loai] = ($this->bc->theoLoai[$loai] ?? 0) + 1;
            $this->bc->theoNguon[$maNguon] = ($this->bc->theoNguon[$maNguon] ?? 0) + 1;
            $this->bc->theoCap[$cap ?? 'chua_xep'] = ($this->bc->theoCap[$cap ?? 'chua_xep'] ?? 0) + 1;
            $this->chuDeNho["{$loai}|{$chuDe}"] = ($this->chuDeNho["{$loai}|{$chuDe}"] ?? 0) + 1;
            $this->bc->dem('the');
        }

        $nhom = $vua = [];

        foreach (array_keys($this->chuDeNho) as $k) {
            $t = $this->chuDe[explode('|', $k)[1]];
            $nhom[(string) $t['gid']] = true;
            $vua[(string) $t['pcode']] = true;
        }

        $this->bc->so['chu_de_nhom'] = count($nhom);
        $this->bc->so['chu_de_vua'] = count($vua);
        $this->bc->so['chu_de_nho'] = count($this->chuDeNho);
    }

    /**
     * @param  array<string, mixed>  $r
     * @return array{string, string, string, string|null}|null mã nguồn, loại, mã chủ đề, cấp; null nếu lỗi
     */
    private function kiemMotThe(array $r, bool $bao = true): ?array
    {
        $loai = (string) $r['collection'];
        $front = trim((string) $r['front']);
        $ten = 'thẻ #'.$r['id']." ({$loai}) \"".Str::limit($front, 60).'"';
        $loi = function (string $chu) use ($bao): void {
            if ($bao) {
                $this->bc->loi($chu);
            }
        };

        // Dòng rỗng (không có chữ tiếng Đức) không hiện được: bỏ qua, chỉ cảnh báo
        if ($front === '') {
            if ($bao) {
                $this->bc->canhBao[] = 'thẻ #'.$r['id']." ({$loai}) không có mặt trước tiếng Đức: bỏ qua.";
            }

            return null;
        }

        if (! isset($this->loaiThe[$loai])) {
            $loi("{$ten}: loại thẻ lạ \"{$loai}\".");

            return null;
        }

        if (mb_strlen($front) > 500 || trim((string) $r['key']) === '') {
            $loi("{$ten}: thiếu khoá, hoặc mặt trước dài quá 500 ký tự.");

            return null;
        }

        $src = (string) ($r['src'] ?? '');
        $maNguon = self::NGUON_THE[$src] ?? null;

        if ($maNguon === null) {
            $loi("{$ten}: nguồn lạ \"{$src}\" (chưa biết thuộc nguồn nào trong content_sources).");

            return null;
        }

        $chuDe = $this->chuDeCua($r);

        if ($chuDe === null) {
            $loi("{$ten}: không có chủ đề nhỏ (mỗi thẻ phải thuộc đúng 1 chủ đề).");

            return null;
        }

        if ($this->loaiThe[$loai] && ! self::laTuDon($front)) {
            $loi("{$ten}: Từ vựng chỉ nhận từ đơn, đây là cụm từ.");

            return null;
        }

        return [$maNguon, $loai, $chuDe, preg_match('/[ABC][12]/', (string) ($r['level'] ?? ''), $m) === 1 ? $m[0] : null];
    }

    /** @param  array<string, mixed>  $r */
    private function chuDeCua(array $r): ?string
    {
        $theme = (string) ($r['theme'] ?? '');

        if (isset($this->chuDe[$theme])) {
            return $theme;
        }

        $doi = $this->doiChuDe[(string) ($r['topic'] ?? '')] ?? null;

        return $doi !== null && isset($this->chuDe[$doi]) ? $doi : null;
    }

    private function kiemPhanKhac(): void
    {
        $chuong = [];

        foreach ($this->nguon->dong("SELECT slug, grp FROM docs WHERE lang = 'de' ORDER BY id") as $r) {
            $ma = self::nguonBai((string) $r['slug']);

            if (! $this->duocBan($ma)) {
                $this->boViNguon($ma);

                continue;
            }

            $this->bc->dem('bai_ngu_phap');
            $c = self::chuongCua((string) ($r['grp'] ?? ''));

            if ($c !== null) {
                $chuong[$c['code']] = true;
            }
        }

        $this->bc->so['chuong_ngu_phap'] = count($chuong);

        foreach ($this->nguon->dong("SELECT s.key, (SELECT COUNT(*) FROM exercises e WHERE e.set_id = s.id) AS n FROM ex_sets s WHERE s.lang = 'de' ORDER BY s.id") as $r) {
            $ma = $this->nguonBoBaiTap((string) $r['key']);

            if ($ma === null) {
                continue;
            }

            if (! $this->duocBan($ma)) {
                $this->boViNguon($ma, 1 + (int) $r['n']);

                continue;
            }

            $this->bc->dem('bo_bai_tap');
            $this->bc->dem('cau_bai_tap', (int) $r['n']);
        }

        // sig là khoá để chạy lại không nhân đôi: trùng thì hai câu nhập thành một, dài quá thì database từ chối
        $sigCua = [];

        foreach ($this->nguon->dong("SELECT e.id, e.kind, e.answers, e.sig, s.key FROM exercises e JOIN ex_sets s ON s.id = e.set_id WHERE s.lang = 'de' ORDER BY e.id") as $r) {
            $cau = 'câu bài tập #'.$r['id'].' (bộ '.$r['key'].')';

            if (! in_array($r['kind'], self::KIEU_BAI_TAP, true) || ! is_array(json_decode((string) $r['answers'], true))) {
                $this->bc->loi($cau.': dạng "'.$r['kind'].'" lạ hoặc đáp án không phải danh sách JSON.');
            }

            $sig = self::sigCua($r);

            if ($sig === null) {
                continue;
            }

            if (mb_strlen($sig) > self::SIG_TOI_DA) {
                $this->bc->loi($cau.': sig dài quá '.self::SIG_TOI_DA.' ký tự.');
            } elseif (isset($sigCua[$sig])) {
                $this->bc->loi($cau.': sig "'.$sig.'" trùng với câu #'.$sigCua[$sig].'.');
            }

            $sigCua[$sig] ??= (int) $r['id'];
        }

        $dongTu = $this->nguon->mot("SELECT COUNT(*) AS n FROM verbs WHERE lang = 'de'");

        if ($this->duocBan('lernbuch_xlsx')) {
            $this->bc->so['dong_tu'] = (int) ($dongTu['n'] ?? 0);
        } else {
            $this->boViNguon('lernbuch_xlsx', (int) ($dongTu['n'] ?? 0));
        }
    }

    private static function nguonBai(string $slug): string
    {
        return match (true) {
            str_starts_with($slug, 'g-') => 'grammar_outline',
            $slug === 'slang' => 'german_slang',
            default => 'lernbuch_xlsx',
        };
    }

    private function nguonBoBaiTap(string $khoa): ?string
    {
        $ma = self::NGUON_BO_BAI_TAP[explode(':', $khoa)[0]] ?? null;

        if ($ma === null) {
            $this->bc->loi("bộ bài tập \"{$khoa}\": tiền tố khoá lạ (chưa biết thuộc nguồn nào).");
        }

        return $ma;
    }

    /** @return array{code: string, name: string, sort: int, level: int}|null chương ngữ pháp từ "A1|1|Danh từ & mạo từ" */
    private static function chuongCua(string $grp): ?array
    {
        $p = explode('|', $grp);

        if (count($p) !== 3 || ! isset(self::CAP[$p[0]]) || ! ctype_digit($p[1])) {
            return null;
        }

        return ['code' => $p[0].'-'.$p[1], 'name' => trim($p[2]), 'sort' => self::CAP[$p[0]] * 100 + (int) $p[1], 'level' => self::CAP[$p[0]]];
    }

    // ------------------------------------------------------------------ ghi

    /** @return array<string, int> mã chủ đề nhỏ cũ "loại|mã" → id */
    private function ghiChuDe(): array
    {
        $nhom = $vua = $nho = [];
        $slug = [];
        $slugMoi = function (string $goc, string $ma) use (&$slug): string {
            $s = Str::slug($goc) ?: Str::slug($ma);
            $s = isset($slug[$s]) ? $s.'-'.Str::slug($ma) : $s;
            $slug[$s] = true;

            return Str::limit($s, 160, '');
        };

        foreach ($this->chuDeNho as $k => $soThe) {
            [$loai, $ma] = explode('|', $k);
            $t = $this->chuDe[$ma];
            [$tenNhom, $tenVua] = explode(' › ', (string) $t['pvi'], 2);
            $gid = (string) $t['gid'];
            $pcode = (string) $t['pcode'];
            $nhom[$gid] ??= ['ten' => $tenNhom, 'sort' => (int) preg_replace('/\D/', '', $gid)];
            $vua[$pcode] ??= ['ten' => $tenVua, 'nhom' => $gid, 'sort' => (int) ($t['sort'] ?? 0)];
            $nho[$k] = ['loai' => $loai, 'ma' => "{$ma}.".self::DUOI_LOAI[$loai], 't' => $t, 'vua' => $pcode, 'so' => $soThe];
        }

        $dong = fn (int $depth, ?int $cha, ?string $loai, string $ma, string $s, string $vi, ?string $en, ?string $icon, int $sort, int $soThe) => [
            'lang' => self::LANG, 'parent_id' => $cha, 'depth' => $depth, 'collection' => $loai, 'code' => $ma, 'slug' => $s,
            'name_vi' => Str::limit($vi, 160, ''), 'name_en' => $en !== null ? Str::limit($en, 160, '') : null, 'icon' => $icon,
            'sort' => $sort, 'card_count' => $soThe, 'status' => 'published', 'created_at' => $this->bay, 'updated_at' => $this->bay,
        ];
        $capNhat = ['parent_id', 'depth', 'collection', 'slug', 'name_vi', 'name_en', 'icon', 'sort', 'card_count', 'status', 'updated_at'];
        $daCo = DB::table('topics')->where('lang', self::LANG)->pluck('id', 'code')->all();

        $hang = [];
        foreach ($nhom as $gid => $g) {
            $hang[] = $dong(1, null, null, $gid, $slugMoi($g['ten'], $gid), $g['ten'], null, null, $g['sort'], 0);
        }
        $this->upsertChuDe($hang, $capNhat, $daCo);
        $id = DB::table('topics')->where('lang', self::LANG)->pluck('id', 'code')->map(fn ($v) => (int) $v)->all();

        $hang = [];
        foreach ($vua as $pcode => $p) {
            $hang[] = $dong(2, $id[$p['nhom']], null, $pcode, $slugMoi($p['ten'], $pcode), $p['ten'], null, null, $p['sort'], 0);
        }
        $this->upsertChuDe($hang, $capNhat, $daCo);
        $id = DB::table('topics')->where('lang', self::LANG)->pluck('id', 'code')->map(fn ($v) => (int) $v)->all();

        $hang = [];
        foreach ($nho as $n) {
            $t = $n['t'];
            $icon = isset($t['emoji']) && is_string($t['emoji']) && $t['emoji'] !== '' ? Str::limit($t['emoji'], 40, '') : null;
            $hang[] = $dong(3, $id[$n['vua']], $n['loai'], $n['ma'], $slugMoi($t['vi'].' '.self::SLUG_LOAI[$n['loai']], $n['ma']),
                (string) $t['vi'], isset($t['en']) ? (string) $t['en'] : null, $icon, (int) ($t['sort'] ?? 0), $n['so']);
        }
        $this->upsertChuDe($hang, $capNhat, $daCo);

        // Chủ đề không còn trong lần nhập này (vd thẻ của nó thuộc nguồn vừa đổi sang không có quyền): ẩn đi, giữ id
        $giu = [...array_keys($nhom), ...array_keys($vua), ...array_column($nho, 'ma')];
        $an = DB::table('topics')->where('lang', self::LANG)->whereNotIn('code', $giu)->where('status', '!=', 'archived')
            ->update(['status' => 'archived', 'card_count' => 0, 'updated_at' => $this->bay]);
        $this->bc->ghiNhan('chu_de', 0, 0, $an);

        $id = DB::table('topics')->where('lang', self::LANG)->pluck('id', 'code')->map(fn ($v) => (int) $v)->all();
        $ra = [];
        foreach ($nho as $k => $n) {
            $ra[$k] = $id[$n['ma']];
        }

        return $ra;
    }

    /**
     * @param  list<array<string, mixed>>  $hang
     * @param  list<string>  $capNhat
     * @param  array<string, mixed>  $daCo
     */
    private function upsertChuDe(array $hang, array $capNhat, array $daCo): void
    {
        if ($hang === []) {
            return;
        }

        DB::table('topics')->upsert($hang, ['lang', 'code'], $capNhat);
        $moi = count(array_filter($hang, fn ($h) => ! isset($daCo[$h['code']])));
        $this->bc->ghiNhan('chu_de', $moi, count($hang) - $moi);
    }

    /** @param  array<string, int>  $chuDeId */
    private function ghiThe(array $chuDeId): void
    {
        $lo = [];
        $khoaDaNhap = [];

        foreach ($this->nguon->dong("SELECT * FROM cards WHERE active = 1 AND lang = 'de' ORDER BY src_no, id") as $r) {
            $the = $this->kiemMotThe($r, false);

            if ($the === null || ! $this->duocBan($the[0])) {
                continue;
            }

            $lo[] = $this->dongThe($r, $the, $chuDeId["{$the[1]}|{$the[2]}"]);
            $khoaDaNhap[$the[1]][(string) $r['key']] = true;

            if (count($lo) >= self::LO) {
                $this->ghiLoThe($lo);
                $lo = [];
            }
        }

        $this->ghiLoThe($lo);

        // Thẻ hệ thống không còn trong nguồn (nguồn bị đổi sang không có quyền, hoặc Lernbuch bản mới đã bỏ):
        // nguồn không có quyền thì xoá hẳn; còn lại thì ẩn (giữ id và tiến độ học)
        $nguonCam = array_values(array_filter($this->nguonId, fn ($id, $ma) => ! $this->duocBan($ma), ARRAY_FILTER_USE_BOTH));
        $xoa = 0;

        if ($nguonCam !== []) {
            foreach (DB::table('cards')->where('lang', self::LANG)->whereNull('owner_user_id')->whereIn('source_id', $nguonCam)->pluck('id')->chunk(self::LO) as $ids) {
                DB::transaction(function () use ($ids, &$xoa): void {
                    $ds = $ids->all();
                    DB::table('card_translations')->whereIn('card_id', $ds)->delete();
                    DB::table('card_examples')->whereIn('card_id', $ds)->delete();
                    DB::table('word_forms')->whereIn('card_id', $ds)->delete();
                    $xoa += DB::table('cards')->whereIn('id', $ds)->delete();
                });
            }
        }

        $an = 0;
        foreach (DB::table('cards')->where('lang', self::LANG)->whereNull('owner_user_id')->where('status', 'published')->get(['id', 'collection', 'card_key'])->chunk(self::LO) as $ds) {
            $bo = $ds->filter(fn ($c) => ! isset($khoaDaNhap[$c->collection][$c->card_key]))->pluck('id')->all();

            if ($bo !== []) {
                $an += DB::table('cards')->whereIn('id', $bo)->update(['status' => 'archived', 'updated_at' => $this->bay]);
            }
        }

        $this->bc->ghiNhan('the', 0, 0, $xoa + $an);
    }

    /**
     * @param  array<string, mixed>  $r
     * @param  array{string, string, string, string|null}  $the
     * @return array{the: array<string, mixed>, dich: array<string, string>, vi_du: list<array{sentence: string, translations: array<string, string>}>, dang: list<array{string, string}>}
     */
    private function dongThe(array $r, array $the, int $chuDeId): array
    {
        [$maNguon, $loai, , $cap] = $the;
        $front = trim((string) $r['front']);
        $article = in_array($r['article'], ['der', 'die', 'das'], true) ? (string) $r['article'] : null;
        // "–" trong cột dạng từ là "không có" (danh từ không có số nhiều), khác với để trống là chưa có dữ liệu: giữ nguyên
        $forms = is_scalar($r['forms']) && trim((string) $r['forms']) === '–' ? '–' : self::chu($r['forms'], 255);
        $dich = array_filter(['vi' => self::chu($r['vi'], 500), 'en' => self::chu($r['en'], 500), 'zh' => self::chu($r['zh'], 500)], fn ($v) => $v !== null);

        $extra = [];
        $extraCu = json_decode((string) ($r['extra'] ?? ''), true);
        $viDuThem = [];

        if (is_array($extraCu) && array_is_list($extraCu)) {
            $viDuThem = $extraCu;
        } elseif (is_array($extraCu)) {
            $extra = $extraCu;
        }

        if (self::chu($r['register'] ?? null, 40) !== null) {
            $extra['register'] = trim((string) $r['register']);
        }

        return [
            'the' => [
                'lang' => self::LANG, 'collection' => $loai, 'card_key' => (string) $r['key'], 'owner_user_id' => null, 'topic_id' => $chuDeId,
                'front' => $front, 'article' => $article, 'gender' => $article !== null ? self::GIONG[$article] : null,
                'pos' => isset($r['pos']) ? (self::LOAI_TU[$r['pos']] ?? 'other') : null, 'forms' => $forms,
                'level' => $cap !== null ? self::CAP[$cap] : null,
                'level_min' => max(1, min(6, (int) ($r['level_min'] ?? 1))), 'level_max' => max(1, min(6, (int) ($r['level_max'] ?? 6))),
                'lektion' => self::chu($r['lektion'] ?? null, 20), 'image' => self::chu($r['emoji'] ?? null, 40), 'image_quality' => max(0, min(3, (int) ($r['emoji_q'] ?? 0))),
                'note' => self::chu($r['note'] ?? null, 65535), 'tip' => self::chu($r['tip'] ?? null, 65535),
                'cloze' => self::chu($r['cloze'] ?? null, 500), 'cloze_answer' => self::chu($r['cloze_ans'] ?? null, 120),
                'source_topic' => self::chu($r['topic'] ?? null, 160), 'extra' => $extra !== [] ? json_encode($extra, JSON_UNESCAPED_UNICODE) : null,
                'search_text' => NoiDung::chuTim($front, $forms, $dich),
                'status' => 'published', 'source_id' => $this->nguonId[$maNguon], 'version' => 1,
                'published_at' => $this->bay, 'created_at' => $this->bay, 'updated_at' => $this->bay,
            ],
            'dich' => $dich,
            'vi_du' => self::cacViDu($r, $viDuThem),
            'dang' => self::cacDang($loai, $front, $forms, isset($r['pos']) ? (string) $r['pos'] : null),
        ];
    }

    /**
     * Câu ví dụ: câu chính (example + bản dịch) trước, rồi exs, câu ví dụ trong extra, ex2; bỏ câu trùng, gộp bản dịch.
     *
     * @param  array<string, mixed>  $r
     * @param  array<int, mixed>  $viDuThem
     * @return list<array{sentence: string, translations: array<string, string>}>
     */
    private static function cacViDu(array $r, array $viDuThem): array
    {
        $ds = [];
        $them = function (mixed $de, array $dich) use (&$ds): void {
            $cau = self::chu($de, 600);

            if ($cau === null || count($ds) >= self::VI_DU_TOI_DA && ! isset($ds[$cau])) {
                return;
            }

            $ds[$cau] ??= [];

            foreach (['vi', 'en', 'zh'] as $l) {
                $d = self::chu($dich[$l] ?? null, 600);

                if ($d !== null && ! isset($ds[$cau][$l])) {
                    $ds[$cau][$l] = $d;
                }
            }
        };

        $them($r['example'] ?? null, ['vi' => $r['example_vi'] ?? null, 'en' => $r['ex_en'] ?? null, 'zh' => $r['ex_zh'] ?? null]);
        $exs = json_decode((string) ($r['exs'] ?? ''), true);

        foreach ([is_array($exs) ? $exs : [], $viDuThem] as $nhom) {
            foreach ($nhom as $e) {
                if (is_array($e)) {
                    $them($e['de'] ?? null, $e);
                }
            }
        }

        $them($r['ex2'] ?? null, []);
        $ra = [];

        foreach ($ds as $cau => $dich) {
            $ra[] = ['sentence' => (string) $cau, 'translations' => $dich];
        }

        return $ra;
    }

    /**
     * Dạng từ để tra khi bấm một chữ trong câu: các cách viết (không mạo từ), số nhiều, dạng chia và so sánh ghi trong forms.
     *
     * @return list<array{string, string}> [dạng viết thường, kiểu]
     */
    private static function cacDang(string $loai, string $front, ?string $forms, ?string $pos): array
    {
        $ra = [];
        $them = function (string $dang, string $kieu) use (&$ra): void {
            $dang = mb_strtolower(NoiDung::catDauCau($dang));

            if ($dang !== '' && mb_strlen($dang) <= 120 && ! in_array([$dang, $kieu], $ra, true)) {
                $ra[] = [$dang, $kieu];
            }
        };

        foreach (in_array($loai, ['vocab', 'name', 'sepverb', 'verbprep'], true) ? self::cacCachViet($front) : [$front] as $c) {
            $them($c, 'lemma');
        }

        if ($forms !== null && $pos === 'Noun' && str_starts_with($forms, 'die ')) {
            foreach (preg_split('/\s*\/\s*/u', mb_substr($forms, 4)) ?: [] as $nhieu) {
                $them((string) preg_replace('/^die\s+/u', '', $nhieu), 'plural');
            }
        } elseif ($forms !== null && str_contains($forms, '·')) {
            $kieu = in_array($pos, ['Adj.', 'Adj', 'Adj./Adv.'], true) ? 'comparative' : 'conj';

            foreach (explode('·', $forms) as $doan) {
                $tu = preg_split('/\s+/u', trim($doan)) ?: [];

                if (count($tu) === 1) {
                    $them($tu[0], $kieu);
                } elseif (count($tu) === 2 && in_array(mb_strtolower($tu[0]), ['er', 'sie', 'es', 'hat', 'ist', 'am'], true)) {
                    $them($tu[1], $kieu);
                }
            }
        }

        return $ra;
    }

    /** @param  list<array{the: array<string, mixed>, dich: array<string, string>, vi_du: list<array{sentence: string, translations: array<string, string>}>, dang: list<array{string, string}>}>  $lo */
    private function ghiLoThe(array $lo): void
    {
        if ($lo === []) {
            return;
        }

        DB::transaction(function () use ($lo): void {
            $hang = array_column($lo, 'the');
            $daCo = DB::table('cards')->where('lang', self::LANG)->whereNull('owner_user_id')
                ->whereIn('card_key', array_column($hang, 'card_key'))->get(['collection', 'card_key'])
                ->map(fn ($c) => $c->collection.'|'.$c->card_key)->flip()->all();
            $capNhat = array_values(array_diff(array_keys($hang[0]), ['lang', 'collection', 'card_key', 'owner_user_id', 'version', 'published_at', 'created_at']));
            DB::table('cards')->upsert($hang, ['lang', 'collection', 'card_key'], $capNhat);
            $moi = count(array_filter($hang, fn ($h) => ! isset($daCo[$h['collection'].'|'.$h['card_key']])));
            $this->bc->ghiNhan('the', $moi, count($hang) - $moi);

            $id = DB::table('cards')->where('lang', self::LANG)->whereNull('owner_user_id')->whereIn('card_key', array_column($hang, 'card_key'))
                ->get(['id', 'collection', 'card_key'])->mapWithKeys(fn ($c) => [$c->collection.'|'.$c->card_key => (int) $c->id])->all();
            $ids = array_values($id);
            DB::table('card_translations')->whereIn('card_id', $ids)->delete();
            DB::table('card_examples')->whereIn('card_id', $ids)->delete();
            DB::table('word_forms')->whereIn('card_id', $ids)->delete();

            $dich = $viDu = $dang = [];

            foreach ($lo as $x) {
                $cardId = $id[$x['the']['collection'].'|'.$x['the']['card_key']];
                $nguon = $x['the']['source_id'];

                foreach ($x['dich'] as $l => $nghia) {
                    $dich[] = ['card_id' => $cardId, 'lang' => $l, 'meaning' => $nghia, 'is_verified' => 0, 'source_id' => $nguon, 'updated_at' => $this->bay];
                }

                foreach ($x['vi_du'] as $i => $v) {
                    $viDu[] = [
                        'card_id' => $cardId, 'sort' => $i, 'sentence' => $v['sentence'],
                        'translations' => $v['translations'] !== [] ? json_encode($v['translations'], JSON_UNESCAPED_UNICODE) : null,
                        'is_manual' => 0, 'source_id' => $nguon, 'created_at' => $this->bay, 'updated_at' => $this->bay,
                    ];
                }

                foreach ($x['dang'] as [$form, $kieu]) {
                    $dang[] = ['lang' => self::LANG, 'form' => $form, 'card_id' => $cardId, 'kind' => $kieu];
                }
            }

            foreach (array_chunk($dich, 1000) as $c) {
                DB::table('card_translations')->insert($c);
            }

            foreach (array_chunk($viDu, 1000) as $c) {
                DB::table('card_examples')->insert($c);
            }

            foreach (array_chunk($dang, 1000) as $c) {
                DB::table('word_forms')->insertOrIgnore($c);
            }

            $this->bc->dem('nghia', count($dich));
            $this->bc->dem('cau_vi_du', count($viDu));
            $this->bc->dem('dang_tu', count($dang));
        });
    }

    /** @return array<string, int> slug bài → id */
    private function ghiNguPhap(): array
    {
        $chuong = $bai = [];

        foreach ($this->nguon->dong("SELECT * FROM docs WHERE lang = 'de' ORDER BY sort, id") as $r) {
            $maNguon = self::nguonBai((string) $r['slug']);

            if (! $this->duocBan($maNguon)) {
                continue;
            }

            $c = self::chuongCua((string) ($r['grp'] ?? ''));

            if ($c !== null) {
                $chuong[$c['code']] = $c;
            }

            $blocks = json_decode((string) ($r['blocks'] ?? '[]'), true);
            $bai[] = [
                'lang' => self::LANG, 'base_lang' => 'vi', 'chuong' => $c['code'] ?? null, 'slug' => Str::limit((string) $r['slug'], 160, ''),
                'level' => self::CAP[(string) ($r['level'] ?? '')] ?? null, 'title' => Str::limit(trim((string) $r['title']), 200, ''),
                'blocks' => json_encode(is_array($blocks) ? $blocks : [], JSON_UNESCAPED_UNICODE), 'status' => 'published',
                'source_id' => $this->nguonId[$maNguon], 'sort' => (int) $r['sort'], 'version' => 1,
                'published_at' => $this->bay, 'created_at' => $this->bay, 'updated_at' => $this->bay,
            ];
        }

        $daCo = DB::table('grammar_chapters')->where('lang', self::LANG)->pluck('id', 'code')->all();
        $hang = array_map(fn ($c) => ['lang' => self::LANG, 'code' => $c['code'], 'name_vi' => Str::limit($c['name'], 120, ''), 'sort' => $c['sort'], 'created_at' => $this->bay, 'updated_at' => $this->bay], array_values($chuong));

        if ($hang !== []) {
            DB::table('grammar_chapters')->upsert($hang, ['lang', 'code'], ['name_vi', 'sort', 'updated_at']);
        }

        $moi = count(array_filter($hang, fn ($h) => ! isset($daCo[$h['code']])));
        $this->bc->ghiNhan('chuong_ngu_phap', $moi, count($hang) - $moi);
        $chuongId = DB::table('grammar_chapters')->where('lang', self::LANG)->pluck('id', 'code')->all();

        $daCo = DB::table('grammar_lessons')->where('lang', self::LANG)->where('base_lang', 'vi')->pluck('id', 'slug')->all();
        $hang = array_map(function ($b) use ($chuongId) {
            $b['chapter_id'] = $b['chuong'] !== null ? (int) $chuongId[$b['chuong']] : null;
            unset($b['chuong']);

            return $b;
        }, $bai);

        foreach (array_chunk($hang, 100) as $c) {
            DB::table('grammar_lessons')->upsert($c, ['lang', 'base_lang', 'slug'], ['chapter_id', 'level', 'title', 'blocks', 'status', 'source_id', 'sort', 'updated_at']);
        }

        $moi = count(array_filter($hang, fn ($h) => ! isset($daCo[$h['slug']])));
        $xoa = $this->xoaTheoNguon('grammar_lessons');
        $this->bc->ghiNhan('bai_ngu_phap', $moi, count($hang) - $moi, $xoa);

        /** @var array<string, int> $id */
        $id = DB::table('grammar_lessons')->where('lang', self::LANG)->where('base_lang', 'vi')->pluck('id', 'slug')->map(fn ($v) => (int) $v)->all();

        return $id;
    }

    /** @param  array<string, int>  $baiId */
    private function ghiBaiTap(array $baiId): void
    {
        $bo = [];
        $nguonBo = [];

        foreach ($this->nguon->dong("SELECT * FROM ex_sets WHERE lang = 'de' ORDER BY sort, id") as $r) {
            $maNguon = $this->nguonBoBaiTap((string) $r['key']);

            if ($maNguon === null || ! $this->duocBan($maNguon)) {
                continue;
            }

            $grp = (string) ($r['grp'] ?? '');
            $slug = Str::limit(str_replace(':', '-', (string) $r['key']), 160, '');
            $nguonBo[(int) $r['id']] = [$slug, $this->nguonId[$maNguon]];
            $bai = $baiId[(string) ($r['gslug'] ?? '')] ?? $baiId[(string) ($r['doc_slug'] ?? '')] ?? null;
            $bo[] = [
                'lang' => self::LANG, 'base_lang' => 'vi', 'slug' => $slug, 'title' => Str::limit(trim((string) $r['title']), 200, ''),
                'section' => match ($grp) {
                    'Số nhiều' => 'plural',
                    'Từ vựng' => 'review',
                    default => 'level',
                },
                'level' => self::CAP[(string) ($r['level'] ?? '')] ?? null, 'grammar_lesson_id' => $bai,
                'description' => self::chu($r['note'] ?? null, 500), 'group_name' => self::chu($r['sub'] ?? null, 160),
                'item_count' => (int) ($r['n'] ?? 0), 'status' => 'published', 'source_id' => $this->nguonId[$maNguon], 'sort' => (int) $r['sort'],
                'created_at' => $this->bay, 'updated_at' => $this->bay,
            ];
        }

        $daCo = DB::table('exercise_sets')->where('lang', self::LANG)->where('base_lang', 'vi')->pluck('id', 'slug')->all();

        foreach (array_chunk($bo, 200) as $c) {
            DB::table('exercise_sets')->upsert($c, ['lang', 'base_lang', 'slug'], ['title', 'section', 'level', 'grammar_lesson_id', 'description', 'group_name', 'item_count', 'status', 'source_id', 'sort', 'updated_at']);
        }

        $moi = count(array_filter($bo, fn ($h) => ! isset($daCo[$h['slug']])));
        $xoaBo = $this->xoaTheoNguon('exercise_sets');
        $this->bc->ghiNhan('bo_bai_tap', $moi, count($bo) - $moi, $xoaBo);
        $boId = DB::table('exercise_sets')->where('lang', self::LANG)->where('base_lang', 'vi')->pluck('id', 'slug')->all();

        $daCo = DB::table('exercises')->pluck('id', 'sig')->all();
        $lo = [];
        $moi = $tong = 0;

        foreach ($this->nguon->dong('SELECT * FROM exercises ORDER BY set_id, sort, id') as $r) {
            if (! isset($nguonBo[(int) $r['set_id']])) {
                continue;
            }

            [$slugBo, $nguon] = $nguonBo[(int) $r['set_id']];
            $sig = self::sigCua($r) ?? substr(sha1($slugBo.'|'.$r['sort'].'|'.$r['q']), 0, 14);
            $extra = array_filter([
                'label' => self::chu($r['label'] ?? null, 160),
                'case_sensitive' => (bool) ($r['cs'] ?? 0) ?: null,
            ], fn ($v) => $v !== null);
            $extraCu = json_decode((string) ($r['extra'] ?? ''), true);

            if (is_array($extraCu)) {
                $extra += $extraCu;
            }

            $lo[] = [
                'set_id' => (int) $boId[$slugBo], 'sig' => $sig, 'kind' => (string) $r['kind'], 'prompt' => (string) $r['q'],
                'answers' => json_encode(json_decode((string) $r['answers'], true), JSON_UNESCAPED_UNICODE),
                'choices' => is_array($ch = json_decode((string) ($r['options'] ?? ''), true)) ? json_encode($ch, JSON_UNESCAPED_UNICODE) : null,
                'hint' => self::chu($r['hint'] ?? null, 500), 'explanation' => self::chu($r['expl'] ?? null, 65535),
                'extra' => $extra !== [] ? json_encode($extra, JSON_UNESCAPED_UNICODE) : null,
                'sort' => (int) $r['sort'], 'status' => 'published', 'source_id' => $nguon, 'created_at' => $this->bay, 'updated_at' => $this->bay,
            ];
            $moi += isset($daCo[$sig]) ? 0 : 1;
            $tong++;

            if (count($lo) >= 1000) {
                DB::table('exercises')->upsert($lo, ['sig'], ['set_id', 'kind', 'prompt', 'answers', 'choices', 'hint', 'explanation', 'extra', 'sort', 'status', 'source_id', 'updated_at']);
                $lo = [];
            }
        }

        if ($lo !== []) {
            DB::table('exercises')->upsert($lo, ['sig'], ['set_id', 'kind', 'prompt', 'answers', 'choices', 'hint', 'explanation', 'extra', 'sort', 'status', 'source_id', 'updated_at']);
        }

        $this->bc->ghiNhan('cau_bai_tap', $moi, $tong - $moi, $this->xoaTheoNguon('exercises'));
    }

    private function ghiDongTu(): void
    {
        if (! $this->duocBan('lernbuch_xlsx')) {
            $this->bc->ghiNhan('dong_tu', 0, 0, $this->xoaTheoNguon('verbs'));

            return;
        }

        $theId = [];
        foreach (DB::table('cards')->where('lang', self::LANG)->whereNull('owner_user_id')->where('collection', 'vocab')->where('status', 'published')->orderBy('id')->get(['id', 'front']) as $c) {
            foreach (self::cacCachViet((string) $c->front) as $v) {
                $theId[mb_strtolower($v)] ??= (int) $c->id;
            }
        }

        $daCo = DB::table('verbs')->where('lang', self::LANG)->pluck('id', 'infinitive')->all();
        $hang = $dang = [];

        foreach ($this->nguon->dong("SELECT * FROM verbs WHERE lang = 'de' ORDER BY sort, id") as $r) {
            $inf = trim((string) $r['inf']);
            $perf = (string) ($r['perf'] ?? '');
            $conj = json_decode((string) ($r['conj'] ?? ''), true);
            $conj = is_array($conj) ? $conj : [];

            if (self::chu($r['example'] ?? null, 600) !== null) {
                $conj['example'] = trim((string) $r['example']);
            }

            $cardId = $theId[mb_strtolower((string) preg_replace('/^sich\s+/u', '', $inf))] ?? null;
            $hang[] = [
                'lang' => self::LANG, 'card_id' => $cardId, 'infinitive' => Str::limit($inf, 80, ''), 'level' => self::CAP[(string) ($r['level'] ?? '')] ?? null,
                'is_separable' => (int) (bool) ($r['sep'] ?? 0), 'prefix' => self::chu($r['prefix'] ?? null, 20),
                'auxiliary' => match (true) {
                    str_starts_with($perf, 'ist ') => 'sein',
                    str_starts_with($perf, 'hat ') => 'haben',
                    default => null,
                },
                'form_du' => self::chu($r['du'] ?? null, 80), 'form_er' => self::chu($r['er'] ?? null, 80),
                'preterite' => self::chu($r['prat'] ?? null, 80), 'perfect' => self::chu($perf, 120),
                'meanings' => json_encode(array_filter(['vi' => self::chu($r['vi'] ?? null, 500), 'en' => self::chu($r['en'] ?? null, 500), 'zh' => self::chu($r['zh'] ?? null, 500)]), JSON_UNESCAPED_UNICODE),
                'conjugation' => $conj !== [] ? json_encode($conj, JSON_UNESCAPED_UNICODE) : null,
                'status' => 'published', 'source_id' => $this->nguonId['lernbuch_xlsx'], 'created_at' => $this->bay, 'updated_at' => $this->bay,
            ];

            // Dạng chia một chữ (không phải động từ tách) để bấm vào chữ trong câu thì ra đúng động từ
            if ($cardId !== null && ! (bool) ($r['sep'] ?? 0)) {
                foreach (['pres', 'prat', 'perf'] as $thi) {
                    foreach (is_array($conj[$thi] ?? null) ? $conj[$thi] : [] as $dangChia) {
                        $tu = preg_split('/\s+/u', trim((string) $dangChia)) ?: [];
                        $chu = $thi === 'perf' ? end($tu) : (count($tu) === 1 ? $tu[0] : null);

                        if (is_string($chu) && $chu !== '' && mb_strlen($chu) <= 120) {
                            $dang[] = ['lang' => self::LANG, 'form' => mb_strtolower($chu), 'card_id' => $cardId, 'kind' => 'conj'];
                        }
                    }
                }
            }
        }

        foreach (array_chunk($hang, 200) as $c) {
            DB::table('verbs')->upsert($c, ['lang', 'infinitive'], ['card_id', 'level', 'is_separable', 'prefix', 'auxiliary', 'form_du', 'form_er', 'preterite', 'perfect', 'meanings', 'conjugation', 'status', 'source_id', 'updated_at']);
        }

        foreach (array_chunk($dang, 1000) as $c) {
            DB::table('word_forms')->insertOrIgnore($c);
        }

        $moi = count(array_filter($hang, fn ($h) => ! isset($daCo[$h['infinitive']])));
        $this->bc->ghiNhan('dong_tu', $moi, count($hang) - $moi);
    }

    /** Xoá dòng nội dung của nguồn không có quyền dùng để bán (đã nhập ở lần trước). */
    private function xoaTheoNguon(string $bang): int
    {
        $cam = array_values(array_filter($this->nguonId, fn ($id, $ma) => ! $this->duocBan($ma), ARRAY_FILTER_USE_BOTH));

        if ($cam === []) {
            return 0;
        }

        $q = DB::table($bang)->whereIn('source_id', $cam);

        return in_array($bang, ['exercises'], true) ? $q->delete() : $q->where('lang', self::LANG)->delete();
    }

    /**
     * Sig của câu bài tập cũ, giữ nguyên văn và không cắt (chạy thử đã kiểm độ dài và trùng); trống thì null.
     *
     * @param  array<string, mixed>  $r
     */
    private static function sigCua(array $r): ?string
    {
        $v = $r['sig'] ?? null;
        $s = is_scalar($v) ? trim((string) $v) : '';

        return $s === '' ? null : $s;
    }

    /** Chữ đã cắt khoảng trắng, rỗng thì null, dài quá thì cắt. */
    private static function chu(mixed $v, int $toiDa): ?string
    {
        if (! is_scalar($v)) {
            return null;
        }

        $s = trim((string) $v);

        return $s === '' || $s === '–' ? null : Str::limit($s, $toiDa, '');
    }
}
