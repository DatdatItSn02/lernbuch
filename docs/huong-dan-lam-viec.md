# Hướng dẫn làm việc cho Claude (Lernbuch Online)

Đọc file này một lần ở đầu phiên, rồi đọc file việc của tuần (`docs/viec-g2-tuan-N.md`). Hai file này thay cho việc dò lại code và đọc lại kế hoạch. Ở đây là luật chung, lệnh hay dùng, bản đồ code và các bẫy đã gặp. Việc cụ thể nằm ở file tuần.

## 1. Luật để nhanh và ít tốn token

1. **Không đọc lại** kế hoạch G2, `phan-tich-du-an.md`, cả file `schema.sql` (1.700 dòng), `seed.sql`, log CI đầy đủ. Cần một bảng: `grep -n "^CREATE TABLE ten_bang " -A 30 database/sql/schema.sql`.
2. **Hỏi đồ thị trước khi mở nhiều file.** `graphify explain "TenLop"` cho biết lớp nằm ở đâu, ai dùng. `graphify query "câu hỏi" --budget 600` cho câu hỏi rộng hơn. `graphify affected "TenLop"` cho biết sửa lớp này thì đụng tới đâu. Đồ thị cũ thì `graphify update .` (12 giây, không tốn token).
3. **Mỗi việc chỉ đọc "file mẫu" ghi ở việc đó**, rồi chép khung. Không đoán API của Filament 5, Pennant, Pest từ trí nhớ khi repo đã có file mẫu.
4. **Quyết định trong file tuần đã chốt.** Không bàn lại, không hỏi lại người dùng. Thiếu chi tiết thì chọn cách đơn giản nhất giống code đang có, rồi ghi một dòng vào mục "Ghi chú" cuối file tuần. Chỉ hỏi người dùng khi: bỏ chức năng hoặc cột dữ liệu, đụng tới bí mật hay deploy, đưa lên `main`.
5. **Không tra web** cho những gì file tuần đã ghi kèm ngày kiểm.
6. **PHP không chạy được ở máy này** (không có `vendor`). Viết xong thì `php -l` từng file, rồi để CI kiểm. Không cài composer, không tải Pint hay PHPStan.
7. **Đẩy theo đợt** như file tuần ghi, không đẩy từng sửa nhỏ. CI mất khoảng 3 phút cho php, 6 phút cho web. Trong lúc CI chạy thì làm việc kế tiếp; chỉ gọi lệnh chờ khi không còn gì làm được.
8. **Đọc CI bằng `tools/ci/xem_ci.py`** (mục 3): một lệnh, chỉ in phần lỗi.
9. **Giao diện:** lúc đang viết chỉ chạy test của file đang sửa; trước khi đẩy mới chạy đủ ba lệnh.
10. **Tích ô ngay khi xong một việc** trong file tuần, commit cùng code. Phiên mới, hoặc sau khi ngữ cảnh bị nén: đọc lại hai file này rồi làm tiếp từ ô chưa tích.
11. **Trả lời người dùng bằng tiếng Việt, ngắn.** Cuối mỗi đợt tối đa 5 dòng: xong gì, CI thế nào, còn gì. Không kể từng bước.
12. Không gọi subagent. Không chạy prettier. Không sửa code ngoài phạm vi việc đang làm.

## 2. Giới hạn bắt buộc

- Không đọc, không ghi file `.env` thật, kể cả trên máy người dùng. Khoá và mật khẩu do người dùng tự nhập vào GitHub Secrets; Claude không bao giờ cầm giá trị.
- Chỉ đẩy nhánh `work/**`. Đưa lên `main` là deploy thật: phải hỏi người dùng, lần nào cũng hỏi.
- Database: không khoá ngoại; thời gian là INT Unix (`$dateFormat = 'U'`), trừ bảng của Laravel; có cột audit. Đổi cấu trúc thì viết migration mới chỉ thêm, sửa `database/sql/schema.sql` cùng lúc.
- Viết chức năng nào thì viết test chức năng đó, có ca lỗi, và chạy trước khi giao.
- `composer.json` ghi PHP `^8.3`: không dùng hàm chỉ có từ 8.4 (`array_find`, `array_any`, `array_all`, `mb_trim`…).
- Chữ giao diện của app học chỉ nằm trong `resources/js/app/i18n/vi.ts`; `tests/js/chuVietThang.test.ts` bắt chữ Việt viết thẳng trong mã. Trang quản trị viết tiếng Việt thẳng trong PHP.
- Commit: một dòng tiếng Việt nói việc đã làm, như các commit trước; hai dòng cuối theo system-reminder hiện hành. Không commit `graphify-out/`, `ket-qua/`, `test-results/`.

## 3. Lệnh hay dùng

```bash
# Trước khi đẩy
for f in $(git diff --name-only origin/main -- '*.php'); do php -l "$f" | grep -v '^No syntax'; done
npx vitest run resources/js/app/components/ai      # chỉ test của thư mục đang sửa
npm run typecheck && npm run lint && npm test      # đủ ba lệnh, ngay trước khi đẩy

# Đẩy rồi xem CI
git push -q origin HEAD
python3 tools/ci/xem_ci.py --cho                   # chờ tới khi có kết quả của HEAD (tối đa 9 phút), in gọn
python3 tools/ci/xem_ci.py                         # xem ngay, không chờ
python3 tools/ci/xem_ci.py --buoc php Pint         # log của một bước: Pint, Larastan, Pest, Vitest, Playwright…

# Tra cứu
graphify explain "HoiAi"
grep -n "^CREATE TABLE ai_requests " -A 26 database/sql/schema.sql
```

Lệnh `--cho` thoát với mã 0 khi cả hai job đạt, 1 khi có job hỏng, 2 khi hết giờ chờ (chạy lại). Thông điệp commit có `[anh]` thì CI chụp ảnh mọi màn: chỉ dùng ở lần đẩy cuối của tuần. Thông điệp có `[skip ci]` thì CI không chạy: chỉ dùng cho commit chỉ sửa tài liệu.

## 4. Bản đồ code

| Cần gì | Ở đâu | Ghi chú |
| --- | --- | --- |
| Lớp xử lý của một khu | `app/Support/<Khu>/` | Lớp `final`, hàm tĩnh, tên tiếng Việt không dấu. `app/Modules/*` chỉ có README, không đặt code ở đó |
| Controller API | `app/Http/Controllers/Api/V1/` | Mỏng: kiểm đầu vào rồi gọi lớp trong `app/Support`. `$this->nguoiDung($request)` trả `User` |
| Đường dẫn API | `routes/api.php` | Một nhóm `v1`, middleware `auth:sanctum`, `con-hoat-dong`, `da-xac-minh`, `ngon-ngu`. Thêm đường mới vào trong nhóm này |
| Lỗi API | `app/Support/LoiApi.php`, `lang/vi/api.php` | `LoiApi::json('ma_loi', 403)` trả `{code, message}`; chữ theo mã nằm ở file lang |
| Cấu hình sửa trong admin | bảng `settings`; mẫu `app/Support/Hoc/CaiDatHoc.php` | Đọc bằng `DB::table('settings')`, ghi bằng `upsert`, giá trị là JSON |
| Trang cài đặt trong admin | `app/Filament/Pages/TrangCaiDatHoc.php`, test `tests/Feature/CaiDatHocTest.php` | Mẫu cho mọi trang cài đặt: `canAccess()`, form, `save()`, ghi nhật ký |
| Trang xem người học | `app/Filament/Resources/NguoiHocs/Pages/ViewNguoiHoc.php` | Mẫu nút hành động có hỏi lại và ghi nhật ký (Khoá, Mở khoá) |
| Nhật ký thao tác nhân viên | `app/Support/NhatKy.php` | `NhatKy::ghi('viec', $model, $truoc, $sau)`; chỉ ghi khi người làm là nhân viên |
| Đầu ngày theo múi giờ người học | `app/Support/Hoc/LapLich.php` | `LapLich::dauNgay($luc, $muiGio)`, `dauNgayMai(...)` |
| Thẻ người học được xem | `app/Support/NoiDung.php` | `NoiDung::cacThe($user)->where('cards.id', $id)->first()`; `NoiDung::nghia([$id])`; `NoiDung::CAP` đổi 1–6 thành A1–C2 |
| Khoá học, trình độ tự chọn | `app/Support/KhoaHoc.php` | `KhoaHoc::choApi($user, KhoaHoc::hienTai($user))['level']` |
| Hồ sơ người đang đăng nhập | `ToiController::duLieu()` | Dữ liệu của `GET /api/v1/toi`; kiểu `Toi` ở `resources/js/app/state/taiKhoan.tsx` |
| Quyền và vai trò | `database/sql/seed.sql` dòng 52–110 | `super_admin` có mọi quyền; `admin` thiếu `settings.manage`, `roles.manage`; `content_editor` chỉ có `content.edit`, `reports.handle` |
| Test PHP | `tests/Feature/*.php` (Pest, không chia thư mục con) | `beforeEach(fn () => $this->seed())`; hàm dùng chung ở `tests/Pest.php`: `nhanVien('admin')`, `dongNhatKy('viec')`, `lucHoc('2026-03-01 08:00')`; nội dung mẫu `(new NoiDungThuSeeder)->tao()` |
| Dữ liệu thử dùng chung PHP và JS | `tests/chung/*.json` | |
| Gọi API từ app | `resources/js/app/lib/api.ts`, `lib/useApi.ts` | `api.lay/sua/gui/xoa`; lỗi là `LoiApi` có `trangThai`, `ma`, `message` |
| Chữ giao diện | `resources/js/app/i18n/vi.ts`, hàm `t('nhom.khoa', {thamSo})` | Kiểu `Khoa` bắt lỗi khoá sai lúc `typecheck` |
| Màn hình, thành phần | `resources/js/app/screens/`, `components/` | Trang vở `Sheet` ở `components/Shell.tsx`; đang tải và lỗi tải ở `components/TaiNoiDung.tsx` |
| Test giao diện | `resources/js/test/fakeApi.ts` (`giaApi`, `toiMau`), `test/render.tsx` | Mẫu: `screens/TheTu.test.tsx` |
| Test trình duyệt | `tests/e2e/*.spec.ts`, `tests/e2e/phien.ts` | Máy chủ PHP thật, database có `NguoiHocThuSeeder` và `NoiDungThuSeeder`. Mẫu có axe, sáng và tối: `tests/e2e/noi-dung.spec.ts` |
| Thiết kế | `DESIGN.md`, `.impeccable/design-full.md` | Dùng lớp và token đang có; không thêm màu, cỡ chữ mới |

**AI đã có từ tuần 1 của G2** (dùng lại, đừng viết lại; chữ ký đầy đủ: `graphify explain "TenLop"`)

| Cần gì | Ở đâu | Ghi chú |
| --- | --- | --- |
| Hỏi AI qua tám chặng | `app/Support/Ai/HoiAi.php` | `HoiAi::hoi($user, $loai, $thamSo, $trich, $kieuNguCanh, $maNguCanh)` trả `['data', 'meta']`; từ chối thì ném `TuChoiAi(ma, trangThai, them)`, `LoiApi` đổi thành JSON |
| Cổng (công tắc, danh sách thử, tuổi, đồng ý) | `TrangThaiAi::kiem($user, $cd)`, `choToi($user)` | Khối `ai` của `GET /toi`: `state` là `hidden`, `need_birth_date`, `underage`, `need_consent`, `ready` |
| Cài đặt AI | `CaiDatAi::tatCa()`, `luu()` | Dòng `ai.<khoá>` trong `settings`; `default_model`, các trần, `trial_daily_cap`, `price_usd_per_mtok` |
| Prompt | `AiPrompt::dangDung($loai)`, `SoanLoiNhac::soan()`, `THAM_SO`, `lamSach()` | Mẫu thay bằng `strtr`; thẻ `<input>` giả bị vô hiệu |
| Khuôn kết quả | `KhuonAi::KHUON`, `doc($loai, $chu)`, `jsonSchema($loai)` | Hỏng thì `null`: không hiện, không lưu cache, không trừ lượt |
| Bộ nối | `app/Support/Ai/BoNoi/`: `BoNoiGemini`, `BoNoiOpenAi`, `BoNoiGia`, `ChonBoNoi` | Test: `aiGia()->kich([...])`, `aiGia()->daNhan`; hết kịch bản thì trả `BoNoiGia::mau()` |
| Hạn mức, cache, trần | `HanMucAi`, `BoNhoAi` (`DUOC_LUU`, `khoa()`, `lay()`, `luu()`), `TranAi` (`chamTran()`, `giuCho()`) | Cache giữ hoa thường; trần ngày theo `America/Los_Angeles` |
| Danh sách thử, đồng ý | `DanhSachThu::co()`, `dat()`; `DongYAi::ghi()`, `dangDongY()` | Phiên bản lời đồng ý `2026-11` |
| Hàm test AI | `tests/Pest.php` | `batAi($doi)`, `nguoiHocAi($doi, $thu, $dongY)`, `aiGia()`, `theMau($front)`, `giaiThich($the)`, `dongAi($user, $soDong, $doi)` |
| Quản trị AI | `app/Filament/Pages/TrangCaiDatAi.php`, `app/Support/QuanTri/AiNguoiHoc.php` | Quyền `ai.manage`; test mẫu `tests/Feature/AiCaiDatTest.php` |
| AI ở app học | `lib/ai.ts`, `components/ai/CongAi.tsx` (`CongAi`, `chuLoi`), `screens/ai/TabHoiAi.tsx` | `coTabAi(toi.ai)`; `MA_DOI_TRANG_THAI` thì gọi `taiLaiToi()`; test mẫu `TabHoiAi.test.tsx` |
| Test trình duyệt AI | `tests/e2e/ai.spec.ts`, `NguoiHocThuSeeder::TAI_KHOAN_AI` | Seeder bật `ai.enabled` và xoá trạng thái AI của các tài khoản thử mỗi lần chạy |

## 5. Bẫy đã gặp

**PHP, Pint, Larastan**

- Pint chạy preset `laravel` trên CI. Chép cách trình bày của file bên cạnh: `! $x` có dấu cách, nối chuỗi không dấu cách (`'a'.$b`), `fn ()` có dấu cách, dấu phẩy cuối ở mảng nhiều dòng, `use` xếp theo bảng chữ cái, không để `use` thừa.
- Larastan mức 5: giá trị lấy từ `DB::table(...)` là `mixed`, phải ép kiểu (`(int)`, `(string)`) hoặc ghi `/** @var ... */` như code đang có.
- `settings.value` là cột JSON: ghi bằng `json_encode`, đọc bằng `json_decode((string) ..., true)`.
- Chuỗi mẫu có ngoặc nhọn của JSON: thay `{ten}` bằng `strtr` với danh sách khoá rõ ràng, đừng dùng regex bắt mọi `{...}`.
- `seed.sql` phải chạy hai lần không nhân đôi; CI đếm 5 gói, 40 quyền lợi, 10 prompt. Đừng thêm dòng vào ba bảng đó.
- Test chạy trên Windows nữa: tách dòng bằng `preg_split('/\R/', ...)`, đừng dùng `explode("\n")`.

**Filament 5.12**

- Tên lớp lấy từ file mẫu: `Filament\Schemas\Schema`, `Filament\Schemas\Components\Section`, `Filament\Forms\Components\TextInput`, `Filament\Actions\Action`.
- Bản dịch tiếng Việt còn thiếu thì thêm vào `lang/vendor/<gói>/vi/`; `tests/Feature/BanDichFilamentTest.php` bắt khoá chưa dịch.
- Nhãn không tự viết hoa (`Resource::titleCaseModelLabel(false)` đã đặt). Nút màu phải qua `tests/Feature/MauQuanTriTest.php`: chỉ dùng bảng màu trong `app/Support/QuanTri/MauQuanTri.php`.
- Trang đăng nhập admin chặn quá 5 lần mỗi phút: test trình duyệt đăng nhập một lần trong `dang-nhap.setup.ts` rồi dùng lại phiên.
- Ô chọn trong trang cài đặt của admin dùng `->native()` (ô chọn gốc của trình duyệt): ô chọn kiểu Filament có nút xoá nhỏ, axe báo `target-size`.
- Trong test Livewire, sau một lần `callAction` bị lỗi kiểm dữ liệu thì tạo `Livewire::test(...)` mới cho lần gọi sau; gọi tiếp trên bản cũ ném `ActionNotResolvableException`.
- Thêm việc vào lịch (`routes/console.php`) làm cột của `schedule:list` đổi độ rộng: test so bằng regex, đừng so nguyên dòng.

**App học và test**

- Màn tải bằng `lazy` (tab Hỏi AI, trang Hỏi AI): trong Vitest phải `await waitFor`/`findBy…` trước khi tìm nội dung.
- Thêm trường vào `Toi` thì sửa cả `toiMau()` trong `resources/js/test/fakeApi.ts`.
- Test trình duyệt chạy song song hai cỡ màn hình trên cùng database: test nào ghi dữ liệu thì mỗi cỡ dùng một tài khoản riêng (`emailHoc(duAn, so)` trong `phien.ts`).
- Service worker bị chặn trong test trình duyệt, trừ `pwa.spec.ts`.
- Cần cập nhật giao diện ngay rồi đặt con trỏ: dùng `flushSync`, không dùng `requestAnimationFrame`.
- Không lồng khung trong khung. Chia phần bằng dải kẻ `border-y border-line py-3`. Vùng bấm tối thiểu `min-h-11`.
- Mọi màn mới có test axe WCAG 2.2 AA ở giao diện sáng và tối, hai cỡ màn hình, kèm `chupDu(page, info, 'ten-man')`.

**CI và máy người dùng**

- `latest-ci-*.txt` trên nhánh `ci-results` là của lần chạy mới nhất, có thể là commit khác. `xem_ci.py` đã so mã commit.
- Đẩy lần nữa lên cùng nhánh thì lần chạy trước bị huỷ.
- Script trong `setup\`: `.ps1` phải có BOM và CRLF, `.bat` chỉ chữ không dấu; sửa thì chạy workflow `windows`.
