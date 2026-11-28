# G2 tuần 3: chấm phát âm và hỗ trợ (danh sách việc)

Viết 29/11/2026. Nhánh `work/g2-tuan3`, tách từ `work/g2-tuan2` (`f0a3902`; tuần 1 và 2 chưa lên `main`). Đọc `docs/huong-dan-lam-viec.md` trước, nhất là hai bảng AI ở mục 4 và mục 5. File này đủ để làm hết tuần 3: không cần mở kế hoạch G2, không cần tra web cho những gì đã ghi ở đây.

**Xong tuần này thì có gì:** người học trong danh sách thử đọc một từ hoặc một câu ví dụ vào micro và nhận điểm (Azure), hoặc bản dự phòng bằng nhận giọng của trình duyệt khi chưa có khoá; mọi người học báo lỗi được ở 5 loại nội dung, gửi yêu cầu ở trang Liên hệ, đọc Câu hỏi thường gặp, đánh dấu bài ngữ pháp đã đọc; nhân viên xử lý báo lỗi và trả lời yêu cầu trong trang quản trị.

**Không làm tuần này:** `docs/viec-giong-doc.md` (người dùng bảo tạm hoãn ngày 29/11); sao lưu database khi deploy, nhập nội dung, trang Tổng quan, trang Từ hay được tra bằng AI (tuần 4); giao diện tiếng Anh, FSRS, mọi việc ghi "gói 29/11" trong tài liệu kế hoạch (G3 trở đi); chấm bài nói tự do (G5); đưa lên `main`; deploy.

## 1. Quyết định đã chốt

### 1.1 File mới, file sửa

```
config/ai.php                                   SỬA: thêm khối azure, phat_am (1.2)
app/Support/PhatAm/CaiDatPhatAm.php             cài đặt pron.* trong bảng settings (1.2)
app/Support/PhatAm/CheDoPhatAm.php              off | azure | browser (1.2)
app/Support/PhatAm/HanMucPhatAm.php             lượt mỗi ngày, giây đã dùng trong tháng (1.2)
app/Support/PhatAm/Wav.php                      đọc và kiểm file WAV (1.3)
app/Support/PhatAm/BoCham.php (interface), BoChamAzure.php, BoChamGia.php, ChonBoCham.php, KetQuaCham.php (1.4)
app/Support/PhatAm/ChamPhatAm.php               đường đi của một lượt chấm (1.5)
app/Support/PhatAm/SoChu.php                    bản dự phòng: so chữ nghe được với câu mẫu (1.6)
app/Models/PronunciationAttempt.php, ContentReport.php, SupportTicket.php, SupportMessage.php, Page.php
app/Http/Controllers/Api/V1/AiPhatAmController.php, BaoLoiController.php, LienHeController.php, TroGiupController.php
app/Http/Controllers/Api/V1/NguPhapController.php       SỬA: thêm daDoc (1.10)
app/Http/Controllers/Api/V1/NoiDungController.php       SỬA: câu ví dụ của thẻ có thêm id
app/Http/Controllers/Api/V1/ToiController.php           SỬA: khối ai có thêm pron
app/Support/HoTro/BaoLoi.php, LienHe.php, TroGiup.php, FaqMau.php
app/Notifications/TraLoiHoTro.php
app/Filament/Resources/BaoLois/…, HoTros/…      app/Filament/Pages/TrangTroGiup.php
app/Filament/Pages/TrangCaiDatAi.php            SỬA: mục Chấm phát âm (1.11)
app/Console/Commands/PhatAmThu.php              .github/workflows/phat-am-thu.yml (1.12)
database/migrations/2026_11_30_000001_trang_tro_giup_mau.php   chỉ thêm một dòng vào pages
resources/js/app/lib/wav.ts, ghiAm.ts, nhanGiong.ts, phatAm.ts, hoTro.ts
resources/js/app/components/ai/ChamPhatAm.tsx   resources/js/app/components/NutBaoLoi.tsx
resources/js/app/screens/hoTro/LienHe.tsx, YeuCau.tsx, TroGiup.tsx
tests/Feature/PhatAm{Wav,Azure,Api,HanMuc,DuPhong,KhongLuu,CaiDat,ThuLenh}Test.php
tests/Feature/BaoLoiTest.php, BaoLoiQuanTriTest.php, LienHeTest.php, LienHeQuanTriTest.php, TroGiupTest.php, NguPhapDaDocTest.php
tests/chung/azure-phat-am.json                  sáu câu trả lời mẫu của Azure (1.4)
tests/e2e/phat-am.spec.ts, ho-tro.spec.ts
```

Không đổi cấu trúc database: `pronunciation_attempts`, `content_reports`, `support_tickets`, `support_messages`, `pages`, `user_lesson_progress` đã có đủ cột. Migration duy nhất chỉ thêm dữ liệu. Không thêm gói PHP hay npm. Năm model mới: `$dateFormat = 'U'`; `PronunciationAttempt`, `SupportMessage` chỉ có `created_at` (`UPDATED_AT = null`).

### 1.2 Cấu hình, chế độ, hạn mức

`config/ai.php` thêm:

```php
'azure' => ['khoa' => env('AZURE_SPEECH_KEY'), 'vung' => env('AZURE_SPEECH_REGION'), 'goc' => env('AZURE_SPEECH_ENDPOINT')],
'phat_am' => ['bo_cham' => env('PRON_DRIVER'), 'cho_toi_da' => 15],   // bo_cham: fake hoặc để trống
```

`CaiDatPhatAm` (chép cách làm của `CaiDatAi`; tên trong `settings` là `pron.` + khoá; `pron.provider`, `pron.max_seconds` đã có trong `seed.sql`, ba khoá còn lại chỉ có mặc định trong code, lưu thì mới thành dòng):

| Khoá | Mặc định | Hợp lệ |
| --- | --- | --- |
| `provider` | `azure` | `azure`, `browser`, `off` |
| `max_seconds` | 15 | 5 đến 30 (Azure nhận tối đa 30 giây cho chấm phát âm) |
| `trial_daily_cap` | 20 | 0 đến 200 |
| `monthly_free_seconds` | 18000 | 0 đến 360000 (gói F0: 5 giờ mỗi tháng) |
| `switch_at_seconds` | 16200 | 0 đến `monthly_free_seconds` (4,5 giờ) |

`CheDoPhatAm::cua(): string`

1. `provider = off` → `off`.
2. `config('ai.phat_am.bo_cham') === 'fake'` và không phải production → `azure` (dùng `BoChamGia`).
3. `provider = azure`, có khoá, có vùng hoặc `goc`, và `HanMucPhatAm::giayThangNay() < switch_at_seconds` → `azure`.
4. Còn lại → `browser`.

`HanMucPhatAm`:

- `giayThangNay()`: tổng `audio_ms` của `pronunciation_attempts` có `provider = 'azure'`, `created_at` từ 00:00 ngày 1 của tháng theo UTC, chia 1.000, làm tròn lên.
- `trangThai($user)` trả `limit`, `used`, `reset_at` như `HanMucAi::trangThai`. `limit` = `QuyenLoi::so($user, 'pron_daily')`; khi `CaiDatAi` đang ở `audience = testers` thì lấy số nhỏ hơn giữa gói và `trial_daily_cap`. `used` = số dòng `provider = 'azure'`, `status = 'ok'` của người đó từ `LapLich::dauNgay(now, timezone)`. Lượt im lặng, lỗi, bản dự phòng không bị trừ.

Khối `ai` của `GET /toi` khi `state = ready` có thêm `pron: {mode, limit, used, reset_at, max_seconds}`. Sửa kiểu `TrangThaiAi` ở `lib/ai.ts` và `toiMau()`.

### 1.3 File ghi âm (`Wav`)

App gửi JSON, trường `audio` là file WAV mã hoá base64. Không dùng multipart, để PHP không ghi file tạm ra đĩa.

`Wav::doc(string $base64, int $giayToiDa): Wav` (ném `TuChoiAi`):

- chuỗi dài hơn `(30 × 32000 + 1000) × 4 / 3` ký tự → `phat_am_qua_lon` (422). Kiểm trước mọi việc khác, trước cả `base64_decode`;
- `base64_decode($x, true)` hỏng; không bắt đầu bằng `RIFF….WAVE`; không tìm thấy khối `fmt ` và `data` (duyệt từng khối, không giả định khối `data` nằm ở byte 44); không phải PCM (mã 1), 1 kênh, 16.000 Hz, 16 bit; khối `data` rỗng → `phat_am_sai_dinh_dang` (422);
- số giây = số byte của `data` / 32.000; lớn hơn `giayToiDa + 0,5` → `phat_am_qua_dai` (422).

Thuộc tính: `bytes` (cả file), `ms`, `imLang` (đúng khi dài dưới 0,3 giây hoặc biên độ lớn nhất dưới 400 trên thang 32.767).

### 1.4 Bộ chấm

```php
interface BoCham { public function cham(string $wav, string $cauMau): KetQuaCham; }
// KetQuaCham: trangThai ('ok' | 'empty' | 'error'), lyDo ('silence' | 'noise' | 'http' | 'timeout' | 'config' | null),
//   tong, chinhXac, troiChay, dayDu (int 0–100 hoặc null), tu (list), daGui (bool: đã gửi âm thanh đi chưa), ms (thời gian chờ)
// tu[]: ['word' => string, 'score' => int|null, 'error' => 'none'|'mispronounced'|'omitted'|'inserted', 'phonemes' => [['p' => string, 'score' => int]] (bỏ khoá này khi Azure không trả)]
```

`BoChamAzure` (tài liệu Microsoft "Speech to text REST API for short audio", kiểm ngày 29/11/2026):

- `POST {goc}?language=de-DE&format=detailed`. `goc` trống thì là `https://{vung}.stt.speech.microsoft.com/speech/recognition/conversation/cognitiveservices/v1`. (Tài liệu hiện ghi thêm dạng `https://<tên resource>.cognitiveservices.azure.com/stt/speech/recognition/conversation/cognitiveservices/v1`; dạng này đặt qua `AZURE_SPEECH_ENDPOINT`.)
- Đầu: `Ocp-Apim-Subscription-Key`, `Accept: application/json`, `Content-Type: audio/wav; codecs=audio/pcm; samplerate=16000`, `Pronunciation-Assessment` = base64 của JSON UTF-8 `{"ReferenceText": câu mẫu, "GradingSystem": "HundredMark", "Granularity": "Phoneme", "Dimension": "Comprehensive", "EnableMiscue": "True"}`. Thân: nguyên file WAV (`Http::withBody`). Chờ tối đa `ai.phat_am.cho_toi_da` giây, không thử lại.
- Trả 200: `RecognitionStatus` = `Success` thì lấy `NBest[0]`: `PronScore` → tổng, `AccuracyScore`, `FluencyScore`, `CompletenessScore` (làm tròn thành số nguyên); `Words[]`: `Word`, `AccuracyScore`, `ErrorType` (`None`, `Mispronunciation`, `Omission`, `Insertion` → bốn giá trị của `error`). Mỗi từ có mảng `Phonemes` (mỗi phần tử `Phoneme`, `AccuracyScore`) thì đưa vào `phonemes`; không có thì thôi. Tên hai trường `Phonemes` này ghi theo trí nhớ, chưa kiểm được: đọc phòng thủ, thiếu là bình thường. `Success` mà không có `NBest` hay `Words` → `empty`, `silence`.
- `NoMatch`, `InitialSilenceTimeout` → `empty`, `silence`. `BabbleTimeout` → `empty`, `noise`. `Error` → `error`, `http`.
- 401, 403 → `error`, `config` (ghi `Log::warning`, không bao giờ kèm khoá). 400, 429, 5xx → `error`, `http`. Hết giờ, đứt mạng → `error`, `timeout`.
- Gửi đi chỉ có: file ghi âm, câu mẫu, mã ngôn ngữ. Không có tên, email, mã tài khoản, IP của người học.

`BoChamGia` (dùng cho test và test trình duyệt; `app(BoChamGia::class)` là singleton như `BoNoiGia`): `kich([...KetQuaCham])` xếp sẵn kết quả, `daNhan` ghi lại `wav` và `cauMau` đã nhận. Hết kịch bản thì trả mẫu: `ok`, tổng 90, chính xác 92, trôi chảy 88, đầy đủ 100, mỗi từ của câu mẫu một dòng `score` 90, `error` `none`. Thêm `chamGia()` vào `tests/Pest.php`.

`tests/chung/azure-phat-am.json`: sáu câu trả lời theo đúng dạng của Azure cho câu mẫu "Ich möchte zwei Brötchen": `dung`, `sai_mot_tu` (từ `zwei` là `Mispronunciation`, 35 điểm), `bo_mot_tu` (`Brötchen` là `Omission`), `co_am` (có `Phonemes`), `im_lang` (`NoMatch`), `on` (`BabbleTimeout`).

### 1.5 Một lượt chấm (`ChamPhatAm::cham`)

`POST ai/phat-am`, thân: đúng một trong `card_id`, `example_id`; `audio`. Theo thứ tự, hỏng ở đâu dừng ở đó:

1. `TrangThaiAi::kiem($user, CaiDatAi::tatCa())`: cùng cổng với 10 chức năng AI (công tắc, danh sách thử, tuổi, đồng ý).
2. Chế độ: `off` → `phat_am_tam_tat` (503); `browser` → `phat_am_doi_che_do` (409).
3. Câu mẫu lấy từ database, không nhận từ app: `card_id` → thẻ trong `NoiDung::cacThe($user)`, câu mẫu là `AmThanh::chuCanDoc(front)`; `example_id` → dòng `card_examples` mà thẻ của nó nằm trong `NoiDung::cacThe($user)`, câu mẫu là `sentence`. Không có, có cả hai, thiếu cả hai → 404 hoặc 422 như Kiểm đáp án.
4. Hạn mức: hết → `phat_am_het_luot` (429) kèm `reset_at`, `limit`; ghi một dòng `ai_requests` trạng thái `quota`.
5. `Wav::doc(...)`.
6. `imLang` → không gọi bộ chấm; kết quả `empty`, `silence`.
7. Gọi bộ chấm.
8. Ghi `pronunciation_attempts`: `provider = 'azure'`, `status` (`ok`, `empty`, `error`), bốn điểm, `words` (mảng `tu`), `audio_ms` (để `NULL` khi `daGui` là sai, để giây của tháng chỉ tính phần đã gửi đi), `card_id` hoặc `example_id`, `reference_text`. Ghi `ai_requests`: `kind = 'pron'`, `provider = 'azure_speech'`, `status` (`ok` cho `ok` và `empty`, `error` cho `error`), `latency_ms`, `input_excerpt` = câu mẫu, ngữ cảnh `card` hoặc `card_example`, token và chi phí bằng 0. Không cột nào chứa âm thanh.
9. `error` → `phat_am_loi_tam_thoi` (503). Còn lại trả `{data: {status, reason, overall, accuracy, fluency, completeness, words, reference}, meta: {mode, limit, used, remaining, reset_at}}`.

Biến chứa file ghi âm chỉ sống trong lượt gọi đó: không `Storage`, không `Log`, không `Cache`, không đưa vào thông báo lỗi.

Mã lỗi mới trong `lang/vi/api.php`:

| Mã | HTTP | Chữ |
| --- | --- | --- |
| `phat_am_tam_tat` | 503 | Chấm phát âm đang tạm tắt. |
| `phat_am_doi_che_do` | 409 | Cách chấm phát âm vừa đổi. Hãy đọc lại. |
| `phat_am_het_luot` | 429 | Bạn đã dùng hết lượt chấm phát âm hôm nay. |
| `phat_am_sai_dinh_dang` | 422 | Bản ghi âm không đúng định dạng. Hãy ghi lại. |
| `phat_am_qua_dai` | 422 | Bản ghi âm dài quá. Hãy đọc ngắn hơn. |
| `phat_am_qua_lon` | 422 | Bản ghi âm lớn quá. Hãy ghi lại. |
| `phat_am_loi_tam_thoi` | 503 | Chưa chấm được, thử lại sau. |
| `khach_khong_gui` | 403 | Chế độ xem thử không gửi được. Hãy đăng nhập. |
| `bao_loi_het_luot` | 429 | Hôm nay bạn đã gửi 10 báo lỗi. Mai gửi tiếp nhé. |
| `lien_he_het_luot` | 429 | Hôm nay bạn đã gửi 5 yêu cầu. Mai gửi tiếp nhé. |
| `lien_he_da_dong` | 422 | Yêu cầu này đã đóng. Hãy gửi yêu cầu mới. |

App: thêm `phat_am_doi_che_do`, `phat_am_tam_tat` vào `MA_DOI_TRANG_THAI` (gọi `taiLaiToi()`).

### 1.6 Bản dự phòng

`POST ai/phat-am/du-phong`, thân: đúng một trong `card_id`, `example_id`; `heard` (chuỗi, tối đa 600, được rỗng). Qua bước 1 và 3 của 1.5; chế độ phải là `browser` (`azure` → `phat_am_doi_che_do`, `off` → `phat_am_tam_tat`). Không trừ lượt, không ghi `ai_requests` (máy chủ không gửi gì đi). Ghi `pronunciation_attempts` với `provider = 'browser'`, `status` `ok` hoặc `empty`, `words`, các điểm `NULL`.

`SoChu::so(string $cauMau, string $ngheDuoc): array`: hai chuỗi đổi chữ thường, bỏ dấu câu, gộp khoảng trắng, `ß` thành `ss` (giữ ä, ö, ü). Đi lần lượt từng từ của câu mẫu, tìm nó trong chuỗi nghe được từ vị trí đang đứng trở đi; thấy thì `heard = true` và dời vị trí. Trả `[['word' => từ gốc trong câu mẫu, 'heard' => bool]]`. `heard` rỗng sau khi làm sạch → `status = empty`.

Trả `{data: {status, words, reference}, meta: {mode}}`.

### 1.7 App: ghi âm và nút chấm

- `lib/wav.ts` (hàm thuần, test được trong Vitest): `maHoaWav(mau: Float32Array): Uint8Array` (đầu 44 byte, PCM 16 bit, 16.000 Hz, 1 kênh), `sangBase64(bytes)`.
- `lib/ghiAm.ts`: `coMicro()`; `batDauGhi({ toiDaGiay, onGiay })` dùng `getUserMedia({ audio: true })` và `MediaRecorder`, tự dừng khi đủ giây, trả `{ dung(): Promise<Blob>, huy() }` và luôn tắt micro khi xong; `sangWav16k(blob)`: `decodeAudioData` rồi `OfflineAudioContext(1, n, 16000)` để về 16 kHz một kênh, rồi `maHoaWav`.
- `lib/nhanGiong.ts`: `coNhanGiong()` (`SpeechRecognition` hoặc `webkitSpeechRecognition`), `nhanGiong({ toiDaGiay })` với `lang = 'de-DE'`, trả `{ text }` hoặc `{ loi: 'khong-nghe' | 'tu-choi' | 'loi' }`.
- `components/ai/ChamPhatAm.tsx`, xuất `NutPhatAm({ cardId?, exampleId?, chu, url? })`. Chỉ hiện khi `toi.ai.state === 'ready'` và `toi.ai.pron.mode !== 'off'`. Biểu tượng lucide `Mic`, khi đang ghi là `Square`. Kết quả hiện ngay dưới nút, không mở hộp thoại.
  - Trạng thái: sẵn sàng → đang ghi (đếm giây `n/15`, nút **Dừng**) → đang chấm (`role="status"`) → kết quả hoặc lỗi (`role="alert"`).
  - Kết quả Azure: **Điểm phát âm** và ba dòng **Chính xác**, **Trôi chảy**, **Đầy đủ**; câu mẫu hiện từng từ, mỗi từ kèm nhãn chữ khi có lỗi: **đọc chưa đúng**, **bỏ sót**, **đọc thừa**. Không chỉ dùng màu: nhãn là chữ. Từ có `phonemes` thì bấm vào hiện điểm từng âm. Dưới cùng: nút nghe mẫu (`AudioButton` với `chu`, `url`), nút **Đọc lại**, dòng `ConLuotAi` cho lượt phát âm.
  - `empty`: `silence` → **Chưa nghe thấy tiếng. Hãy đọc to hơn, gần micro hơn.** `noise` → **Nhiều tiếng ồn quá, chưa nghe rõ. Hãy thử ở nơi yên tĩnh hơn.**
  - Không cho dùng micro: **Trình duyệt chưa được phép dùng micro. Hãy cho phép trong cài đặt của trình duyệt.** Máy không có micro: **Máy này không có micro.**
  - Chế độ `browser`: có `coNhanGiong()` thì ghi bằng `nhanGiong`, gửi `du-phong`, hiện **Trình duyệt nghe được:** và từng từ của câu mẫu kèm nhãn **chưa nghe ra** ở từ `heard = false`, cùng dòng **Bản dự phòng: không có điểm số.** Không có thì hiện **Trình duyệt này không nhận được giọng nói. Hãy nghe mẫu rồi tự so.** và nút nghe mẫu.
- Chỗ đặt: `TheTu.tsx` cạnh nút nghe của từ, và ở mỗi câu ví dụ trong `MeaningTab` (cần `id` của câu ví dụ: `NoiDungController::the` trả thêm `id`); `TraTu.tsx` trong `KetQua`, cho từ.
- Chữ mới vào nhóm `phatAm` của `i18n/vi.ts`. Các chữ in đậm ở mục này dùng nguyên văn.

### 1.8 Báo lỗi nội dung

`POST bao-loi`, thân: `target_type` (`card`, `card_example`, `exercise`, `grammar_lesson`, `verb`), `target_id`, `reason` (`wrong_meaning`, `typo`, `audio`, `grammar`, `other`), `message` (tối đa 1.000; bắt buộc khi `reason = other`). Trả 201 `{data: {id}}`.

- Mục phải có thật và người học xem được: `card` qua `NoiDung::cacThe`; `card_example` qua thẻ của nó; `exercise` qua `BaiTap::cacCau()`; `grammar_lesson` qua `NguPhap::cacBai()`; `verb` qua `DongTu::cacDongTu()`. Không thì 404.
- Tối đa 10 báo lỗi mỗi ngày, ngày theo múi giờ người học (`LapLich::dauNgay`): lượt thứ 11 → `bao_loi_het_luot`. Tài khoản khách xem thử → `khach_khong_gui`.
- Ghi `user_id`, `created_by`, `status = 'open'`.
- `GET ngu-phap/{slug}` trả thêm `id` của bài (app cần để báo lỗi).

App: `components/NutBaoLoi.tsx` (`loai`, `ma`), biểu tượng lucide `Flag`, tên nút **Báo lỗi**. Bấm thì mở ngay tại chỗ: nhóm ô chọn lý do (**Sai nghĩa**, **Lỗi chính tả**, **Âm thanh**, **Ngữ pháp**, **Khác**), ô **Mô tả thêm** (bộ đếm `n/1000`, dùng lại cách của `OHoiAi`), nút **Gửi báo lỗi**, **Huỷ**. Gửi xong: **Đã gửi. Cảm ơn bạn.** Chỗ đặt: `TheTu` (thẻ, và từng câu ví dụ), `BaiNguPhap` (cuối bài), `ChayBai` (sau khi trả lời một câu, cạnh phần giải thích), `DongTu.tsx` (mỗi động từ đang mở). Ẩn với khách xem thử (`toi.guest`).

Quản trị: `BaoLoiResource`, slug `bao-loi`, nhóm "Nội dung", quyền `reports.handle` (thiếu thì 403). Lớp xử lý `BaoLoi`.

- Bảng: lúc gửi, loại mục, mục (chữ rút gọn 60 ký tự: mặt trước thẻ, câu ví dụ, đề bài, tên bài, động từ nguyên mẫu; mục đã bị xoá thì "(không còn)"), lý do, người gửi (email), trạng thái, người nhận. Mặc định lọc **Đang mở** và **Đã nhận**; lọc theo loại, lý do. Mới trước. Không có nút tạo, xoá.
- Trang xem: nội dung báo lỗi, mục bị báo, liên kết **Mở mục để sửa** tới trang sửa của `Thes`, `NguPhaps`, `BoBaiTaps`, `DongTus` (câu ví dụ mở thẻ của nó, câu bài tập mở bộ bài của nó).
- Nút: **Nhận** (`open` → `accepted`, `assigned_to` = mình); **Đã sửa** (→ `fixed`, ghi chú tuỳ chọn, tối đa 500); **Từ chối** (→ `rejected`, ghi chú bắt buộc); **Mở lại** (`fixed`, `rejected` → `open`, xoá `handled_at`). Hai nút kết thúc ghi `handled_at`, `resolution`. Nhật ký `bao_loi.nhan`, `bao_loi.da_sua`, `bao_loi.tu_choi`, `bao_loi.mo_lai`.

### 1.9 Liên hệ và Câu hỏi thường gặp

**Liên hệ.** Lớp xử lý `LienHe`. Tài khoản khách xem thử: mọi đường ghi trả `khach_khong_gui`, đường đọc trả danh sách rỗng.

| Đường | Thân | Ghi chú |
| --- | --- | --- |
| `GET lien-he` | | 30 yêu cầu mới nhất của mình: `id`, `subject`, `category`, `status`, `last_reply_at`, `created_at` |
| `POST lien-he` | `subject` ≤ 200, `category` (`account`, `content`, `bug`, `other`), `body` ≤ 2.000 | 201. `subject`, `body` dùng `ChuNhap::luat`. Tối đa 5 yêu cầu mỗi ngày → `lien_he_het_luot`. Ghi `email` = email người gửi, tin đầu tiên vào `support_messages` |
| `GET lien-he/{id}` | | yêu cầu và mọi tin: `{id, is_staff, body, created_at}`. Không trả tên nhân viên |
| `POST lien-he/{id}/tin` | `body` ≤ 2.000 | 201. Đặt `status = 'open'`. Yêu cầu `closed` → `lien_he_da_dong` |

Của người khác, mã không có → 404 ở cả ba đường có `{id}` (`whereNumber`).

Quản trị: `HoTroResource`, slug `ho-tro`, nhóm "Người dùng", quyền `tickets.handle`.

- Bảng: lúc gửi, chủ đề, loại, email người gửi, trạng thái, lần trả lời cuối. Mặc định lọc **Đang mở** và **Chờ người học**. Mới trước.
- Trang xem: luồng tin (người học và nhân viên), nút **Trả lời** (ô chữ tối đa 2.000): ghi `support_messages` với `is_staff = 1`, `author_id` = mình; `status = 'pending'`, `last_reply_at`; gửi `TraLoiHoTro` tới email của yêu cầu. Nút **Đã xong** (→ `solved`), **Đóng** (→ `closed`, có hỏi lại), **Mở lại** (→ `open`).
- Nhật ký `ho_tro.tra_loi`, `ho_tro.xong`, `ho_tro.dong`, `ho_tro.mo_lai`; không ghi nội dung thư vào nhật ký.
- `TraLoiHoTro`: chép khung `XacMinhEmail` (qua hàng đợi, chữ từ `lang/vi/thu.php` nhóm `ho_tro`, theo ngôn ngữ giao diện của người nhận). Tiêu đề "Lernbuch trả lời yêu cầu: :chu_de"; thân: một dòng mở đầu, nội dung trả lời (chữ thường, không HTML), nút "Xem trong Lernbuch" tới `/lien-he/{id}` của app.

App: `/lien-he` (`LienHe.tsx`: form và danh sách yêu cầu kèm trạng thái **Đang mở**, **Đã trả lời**, **Đã xong**, **Đã đóng**), `/lien-he/:id` (`YeuCau.tsx`: luồng tin, tin của nhân viên ghi **Lernbuch**, ô gửi thêm). Khách xem thử thấy câu **Hãy đăng nhập để gửi yêu cầu.** thay cho form.

**Câu hỏi thường gặp.** Một dòng `pages`: `slug = 'faq.vi'`, `kind = 'help'`, `title = 'Câu hỏi thường gặp'`, `status = 'published'`, `body` là JSON `[{"q": "…", "a": "…"}]`. Mã ngôn ngữ nằm trong slug để G3 thêm `faq.en` mà không đổi cấu trúc.

- Migration thêm dòng này nếu chưa có `faq.vi` (chạy lại không nhân đôi, không ghi đè bản quản trị đã sửa). Chữ 12 câu ở `FaqMau::CAU`, chép từ mục 6 của file này.
- `GET tro-giup/faq` → `{data: {title, items, updated_at}}`. Tìm `faq.<ngôn ngữ giao diện>`, không có thì `faq.vi`, không có nữa thì 404. Chỉ trả trang `published`.
- Quản trị: `TrangTroGiup` (trang Filament, slug `tro-giup`, nhóm "Nội dung", quyền `content.edit`; file mẫu `TrangCaiDatHoc`). Danh sách lặp lại gồm câu hỏi (≤ 200) và trả lời (≤ 1.500), 1 đến 40 mục, kéo đổi thứ tự. Lưu: ghi `body`, `updated_by`, nhật ký `tro_giup.sua` (trước và sau chỉ ghi số câu).
- App: `/tro-giup` (`TroGiup.tsx`): mỗi câu một khối `details`/`summary`; câu trả lời hiện chữ thường, giữ xuống dòng; cuối trang liên kết **Chưa thấy câu trả lời? Gửi yêu cầu ở trang Liên hệ.**
- Lối vào: `CaiDat.tsx` thêm mục **Trợ giúp** với hai liên kết **Câu hỏi thường gặp**, **Liên hệ**. Không thêm mục vào thanh điều hướng.

### 1.10 Đánh dấu bài ngữ pháp đã đọc

- `PUT ngu-phap/{slug}/da-doc`, thân `read` (boolean). Đúng: `updateOrInsert` vào `user_lesson_progress` (`status = 'read'`, `read_at` = bây giờ nếu chưa có). Sai: xoá dòng. Trả `{data: {read}}`. Bài không có → 404. Khách xem thử: không ghi, trả `read: false`.
- `GET ngu-phap` thêm `meta: {read: [slug, …]}` (không đụng `NguPhap::danhSach()`, vì danh sách dùng chung cho mọi người). `GET ngu-phap/{slug}` thêm `read`.
- App: cuối `BaiNguPhap` nút **Đánh dấu đã đọc** (đã đọc thì là **Bỏ đánh dấu đã đọc**, `aria-pressed`); trong danh sách `NguPhap`, bài đã đọc có dấu tích (lucide `Check`) kèm chữ `sr-only` "đã đọc", và dòng phụ của mỗi chương ghi **Đã đọc {n}/{tong}**.

### 1.11 Quản trị chấm phát âm, khoá trong deploy

- `TrangCaiDatAi` thêm mục "Chấm phát âm": ô chọn cách chấm (**Azure**, **Chỉ trình duyệt**, **Tắt**; `->native()`), số giây tối đa, hạn mức giai đoạn thử, giây miễn phí mỗi tháng, ngưỡng chuyển sang bản dự phòng. Hai dòng chỉ đọc: "Khoá Azure: đã có" hoặc "chưa có" (không bao giờ hiện giá trị), và "Tháng này đã dùng X phút trên Y phút miễn phí". Lưu qua `CaiDatPhatAm::luu`, nhật ký `phat_am.cai_dat`.
- `TrangThongKeAi`: thêm một dòng "Chấm phát âm hôm nay: N lượt · tháng này: X phút".
- Khoá: làm đúng như `GEMINI_API_KEY` ở tuần 2 cho `AZURE_SPEECH_KEY`, `AZURE_SPEECH_REGION` (`.env.production.example`, `env-production.py`, bước đóng gói của job deploy, ca mới trong `test_env_production.py`). Chỉ sửa file, không chạy deploy.

### 1.12 Lệnh `app:phat-am-thu` và workflow `phat-am-thu`

Mục đích: biết Azure trả gì cho tiếng Đức trước khi tin vào giao diện, và lấy số liệu cho việc Luyện nói ở G5.

- `php artisan app:phat-am-thu --vao=<thư mục> --ra=<thư mục>`. Thiếu khoá hoặc vùng: in `✘ Chưa có AZURE_SPEECH_KEY`, thoát mã 2, không gọi mạng. Production: từ chối, mã 2. Không in khoá.
- `--vao` có `danh-sach.json`: `[{"id", "file", "cau_mau", "mong_doi"}]`. Mỗi dòng gọi thẳng Azure (không qua cổng, không ghi database), giữa hai lượt nghỉ 1 giây bằng `Sleep`. Ghi lại: mã HTTP, `RecognitionStatus`, bốn điểm, `ErrorType` từng từ, có `Phonemes` không, thời gian chờ.
- `ket-qua.md`: bảng 8 dòng, rồi mục "Kết luận" trả lời bốn câu bằng **có** hoặc **không**: Azure trả điểm từng âm cho tiếng Đức; từ đọc sai bị đánh dấu; từ bỏ sót bị đánh dấu; chấm được khi không có câu mẫu. `ket-qua.json`: nguyên văn 8 câu trả lời. Mã 0 khi cả 8 lượt nhận được trả lời HTTP 200, không thì 1.
- `phat-am-thu.yml`: chạy khi bấm (`workflow_dispatch`) hoặc khi đẩy nhánh `phat-am/thu`. Dựng PHP như `ai-thu.yml`. Tạo 8 file WAV 16 kHz một kênh bằng Piper (cài như `audio.yml`, giọng `de_DE-thorsten-medium`) và `ffmpeg`:

| id | Âm thanh | Câu mẫu gửi kèm |
| --- | --- | --- |
| `dung-cau` | "Ich möchte zwei Brötchen, bitte." | như âm thanh |
| `dung-tu` | "das Brötchen" | như âm thanh |
| `sai-tu` | "Ich möchte drei Brötchen, bitte." | "Ich möchte zwei Brötchen, bitte." |
| `bo-tu` | "Ich möchte Brötchen." | "Ich möchte zwei Brötchen, bitte." |
| `im-lang` | 3 giây im lặng (`anullsrc`) | "Guten Morgen." |
| `on` | 3 giây tiếng ồn (`anoisesrc`) | "Guten Morgen." |
| `khong-mau` | "Am Wochenende fahren wir mit der Straßenbahn in die Stadt." | chuỗi rỗng |
| `dai` | bốn câu nối lại, 20 đến 25 giây | như âm thanh |

  Khoá chỉ có ở bước chạy lệnh. Kết quả ghi bằng `report.sh` vào `phat-am-thu/` trên nhánh `ci-results`. Tám lượt tốn khoảng 1 phút trong 5 giờ miễn phí của tháng; không chạy quá 2 lần một ngày.

## 2. Việc cần làm

Làm theo thứ tự. Mỗi đợt một lần đẩy. Trong lúc CI chạy thì làm việc kế tiếp; phần app học (đợt 4) chạy được test ở máy này nên làm xen vào lúc chờ CI.

### Đợt 1: chấm phát âm ở máy chủ

- [ ] **T1. Cấu hình, chế độ, hạn mức.** `config/ai.php`, `CaiDatPhatAm`, `CheDoPhatAm`, `HanMucPhatAm`, model `PronunciationAttempt`, khối `pron` của `GET /toi` (1.2). File mẫu: `CaiDatAi`, `HanMucAi`.
  - `PhatAmHanMucTest`: các nhánh của `CheDoPhatAm` (tắt; bộ chấm giả; có khoá; thiếu khoá; đủ khoá nhưng tháng này đã 16.200 giây; bộ chấm giả ở production bị bỏ qua); giây của tháng không tính dòng tháng trước, không tính bản dự phòng, không tính dòng `audio_ms` rỗng; hạn mức ngày chỉ đếm `ok`; giai đoạn thử lấy số nhỏ hơn; qua nửa đêm theo múi giờ người học thì có lượt mới; `GET /toi` có `pron` khi `ready`, không có ở các trạng thái khác.
- [ ] **T2. `Wav` và bộ chấm.** `Wav`, `BoCham`, `BoChamAzure`, `BoChamGia`, `ChonBoCham`, `KetQuaCham`, `tests/chung/azure-phat-am.json`, `chamGia()` (1.3, 1.4). File mẫu: `BoNoiGemini`, `BoNoiGia`.
  - `PhatAmWavTest`: file đúng; khối `LIST` nằm trước `data`; 8 kHz, hai kênh, 8 bit, không phải PCM, thiếu `data`, không phải base64, MP3 đổi đuôi → `phat_am_sai_dinh_dang`; 15,4 giây qua, 15,6 giây → `phat_am_qua_dai`; chuỗi quá dài → `phat_am_qua_lon` mà không giải mã; im lặng và dưới 0,3 giây → `imLang`.
  - `PhatAmAzureTest` (`Http::fake`): địa chỉ, tham số, bốn đầu gửi đi; đầu `Pronunciation-Assessment` giải mã ra đúng JSON và giữ nguyên chữ có dấu; thân là đúng file WAV; sáu mẫu trong `azure-phat-am.json` ra đúng `KetQuaCham` (điểm và lỗi từng từ khớp từng số); 401, 403, 400, 429, 500, hết giờ ra đúng `lyDo`; khoá không xuất hiện trong log; `AZURE_SPEECH_ENDPOINT` thắng vùng.
- [ ] **T3. Hai đường API.** `ChamPhatAm`, `SoChu`, `AiPhatAmController`, hai đường trong nhóm `v1`, mã lỗi mới, `id` của câu ví dụ (1.5, 1.6).
  - `PhatAmApiTest` (mục 13): đọc đúng, sai một từ, bỏ một từ (dùng `chamGia()->kich`) trả đúng điểm và từng từ, lưu đúng hai dòng; im lặng không gọi bộ chấm, không trừ lượt; tiếng ồn; quá 15 giây; không phải WAV; quá lớn; bộ chấm lỗi → 503, không trừ lượt, dòng `error`; hết lượt → 429 kèm `reset_at`, bộ chấm không bị gọi; theo thẻ và theo câu ví dụ; thẻ của người khác, câu ví dụ không có → 404; có cả hai hoặc thiếu cả hai → 422; chế độ `browser` → 409, `off` → 503.
  - `PhatAmDuPhongTest` (mục 14): `SoChu` với hoa thường, dấu câu, `ß`/`ss`, từ lặp, từ thừa, chuỗi rỗng; lưu dòng `browser` không có điểm; không ghi `ai_requests`; không trừ lượt; chế độ `azure` → 409.
  - `PhatAmKhongLuuTest` (mục 14): sau 20 lượt chấm (đủ `ok`, `empty`, `error`), thư mục `storage/` không có thêm file nào, không cột nào của `pronunciation_attempts`, `ai_requests` chứa `RIFF` hay 200 ký tự đầu của chuỗi base64; log không chứa chuỗi base64.
  - Thêm vào `AiCongTest` (mục 6, 7): hai đường phát âm × năm ca cổng. Thêm vào `AiDuLieuGuiTest` (mục 8): yêu cầu gửi Azure không có tên, email, `public_id`, IP của người học.
- [ ] **Đẩy đợt 1.**

### Đợt 2: hỗ trợ, ở máy chủ và quản trị

- [ ] **T4. Báo lỗi.** Model, `BaoLoi`, `BaoLoiController`, `BaoLoiResource`, `id` trong `GET ngu-phap/{slug}` (1.8). File mẫu: `NhatKyAis/` (bảng có lọc), `ViewAiPrompt.php` (nút, nhật ký).
  - `BaoLoiTest` (mục 15): gửi được ở cả 5 loại, gắn đúng mục; mục không có, thẻ của người khác, câu bài tập nháp → 404; loại lạ, lý do lạ, chữ 1.001 ký tự, `other` thiếu chữ → 422; lượt thứ 11 trong ngày bị chặn, sang ngày mới theo múi giờ người học gửi lại được; khách xem thử → 403.
  - `BaoLoiQuanTriTest`: thiếu `reports.handle` → 403; `content_editor` vào được; bảng mặc định không hiện mục đã xong; bốn nút đổi đúng trạng thái, `assigned_to`, `handled_at`, có nhật ký; Từ chối thiếu ghi chú báo lỗi ở ô; mục đã bị xoá vẫn mở được trang xem.
- [ ] **T5. Liên hệ.** Hai model, `LienHe`, `LienHeController`, `HoTroResource`, `TraLoiHoTro`, nhóm `ho_tro` trong `lang/vi/thu.php` (1.9).
  - `LienHeTest` (mục 15): gửi, xem danh sách, xem một yêu cầu, gửi thêm tin; của người khác → 404 ở ba đường; yêu cầu thứ 6 trong ngày bị chặn; chữ quá dài, toàn ký hiệu → 422; yêu cầu đã đóng không gửi thêm được; khách xem thử → 403.
  - `LienHeQuanTriTest`: thiếu `tickets.handle` → 403; Trả lời ghi tin, đổi trạng thái, gửi đúng một thư tới đúng địa chỉ (`Notification::fake`), thư có nội dung trả lời và liên kết; câu trả lời hiện ở `GET lien-he/{id}` của người học; người học không thấy tên nhân viên; nhật ký không chứa nội dung thư.
- [ ] **T6. Câu hỏi thường gặp.** Model `Page`, `FaqMau`, migration, `TroGiup`, `TroGiupController`, `TrangTroGiup` (1.9).
  - `TroGiupTest`: database mới có `faq.vi` với 12 câu; chạy lại migration không nhân đôi, không ghi đè bản đã sửa; ngôn ngữ khác lùi về `faq.vi`; trang nháp → 404; thiếu `content.edit` → 403; lưu 0 câu hoặc 41 câu báo lỗi; lưu đúng thì API trả bản mới, có nhật ký.
- [ ] **T7. Đã đọc.** `NguPhapController::daDoc`, `meta.read`, `read` (1.10).
  - `NguPhapDaDocTest`: đánh dấu, bỏ đánh dấu, đánh dấu hai lần vẫn một dòng và giữ `read_at` cũ; người A không thấy dấu của người B; bài không có → 404; `read` không phải boolean → 422; khách xem thử không ghi.
- [ ] **Đẩy đợt 2.**

### Đợt 3: quản trị phát âm, khoá, thử với Azure thật

- [ ] **T8. Cài đặt và khoá.** Mục "Chấm phát âm" của `TrangCaiDatAi`, dòng mới ở `TrangThongKeAi`, hai khoá Azure trong deploy (1.11). README: thêm mục chấm phát âm (hai secret, workflow `phat-am-thu`, cách tắt).
  - `PhatAmCaiDatTest`: lưu từng ô, giá trị ngoài khoảng báo lỗi đúng ô, ngưỡng lớn hơn giây miễn phí báo lỗi; trang không bao giờ chứa giá trị khoá; đổi sang "Tắt" thì `GET /toi` trả `mode = off` và API trả 503; có nhật ký. Thêm ca vào `test_env_production.py`.
- [ ] **T9. `app:phat-am-thu` và workflow.** Theo 1.12. File mẫu: `AiThu.php`, `AiThuLenhTest.php`, `ai-thu.yml`, `audio.yml`.
  - `PhatAmThuLenhTest` (`Http::fake`): gửi đúng 8 lượt; thiếu khoá → mã 2, 0 lượt; production → mã 2; một lượt 500 → mã 1; bốn câu kết luận ra đúng **có**/**không** theo dữ liệu giả; khoá không có trong hai file kết quả.
- [ ] **Đẩy đợt 3.** Rồi đẩy nhánh `phat-am/thu` một lần: có secret thì đọc `phat-am-thu/ket-qua.md` trên `ci-results` và ghi bốn kết luận vào mục 5; chưa có secret thì ghi là đang chờ khoá. Tên trường của Azure khác với 1.4 thì sửa `BoChamAzure` và mẫu trong `azure-phat-am.json` cho khớp.

### Đợt 4: app học

- [ ] **T10. Ghi âm.** `lib/wav.ts`, `ghiAm.ts`, `nhanGiong.ts`, `phatAm.ts` (1.7).
  - `wav.test.ts`: đầu file đúng từng trường (16.000 Hz, 1 kênh, 16 bit, độ dài); mẫu ngoài khoảng −1 đến 1 bị kẹp; mảng rỗng ra file 44 byte; base64 của 1, 2, 3 byte. `ghiAm`, `nhanGiong` kiểm ở test của thành phần bằng đối tượng giả.
- [ ] **T11. Nút chấm phát âm.** `ChamPhatAm.tsx`, đặt vào `TheTu`, `TraTu` (1.7).
  - `ChamPhatAm.test.tsx`: `hidden`, `mode = off`, chưa `ready` thì không có nút; ghi rồi dừng gửi đúng thân; tự dừng khi đủ giây; kết quả hiện bốn điểm và nhãn chữ ở từ sai, bỏ sót, đọc thừa; `silence`, `noise` hiện đúng câu; từ chối micro, không có micro; 429 hiện giờ có lượt mới; 409 gọi `taiLaiToi`; chế độ `browser` có và không có nhận giọng; micro luôn được tắt sau mỗi lượt, kể cả khi lỗi và khi rời trang.
- [ ] **T12. Nút báo lỗi.** `NutBaoLoi.tsx`, `lib/hoTro.ts`, đặt vào năm chỗ (1.8).
  - `NutBaoLoi.test.tsx`: gửi đúng `target_type`, `target_id` ở từng chỗ; `Khác` thiếu chữ thì nút gửi tắt; quá 1.000 ký tự; 429 hiện câu báo; gửi xong hiện lời cảm ơn và đóng form; khách xem thử không thấy nút.
- [ ] **T13. Liên hệ, Trợ giúp, đã đọc.** Ba màn mới, ba đường trong `App.tsx` (tải bằng `lazy`), mục Trợ giúp trong `CaiDat`, nút và dấu tích ở `NguPhap.tsx` (1.9, 1.10).
  - Mỗi màn một file test: danh sách rỗng; gửi form; lỗi 422 hiện ở ô; yêu cầu không có hiện Không tìm thấy; tin của nhân viên ghi "Lernbuch"; yêu cầu đã đóng không có ô gửi; Trợ giúp mở và đóng từng câu bằng bàn phím; đánh dấu đã đọc đổi nút và dấu tích, lỗi mạng thì trả nút về trạng thái cũ.
- [ ] **T14. Test trình duyệt và hoàn tất.** `playwright.config.ts`: hai dự án thêm `permissions: ['microphone']` và `launchOptions.args` `--use-fake-ui-for-media-stream`, `--use-fake-device-for-media-stream`. Job web của `ci.yml`: thêm `PRON_DRIVER: fake`. `NguoiHocThuSeeder`: `SO_TAI_KHOAN_HOC = 17`; tài khoản 16 như 14, 15 (dùng cho phát âm); tài khoản 17 dùng cho báo lỗi, liên hệ, đã đọc; mỗi lần chạy seeder xoá lượt chấm, báo lỗi, yêu cầu, dấu đã đọc của hai tài khoản đó. Sửa `NguoiHocThuSeederTest`.
  - `tests/e2e/phat-am.spec.ts` (mục 13, hai cỡ màn hình, bộ chấm giả, micro giả của Chromium): trên một thẻ, ghi 2 giây rồi dừng, thấy điểm và từng từ; đọc câu ví dụ; Đọc lại; axe sáng và tối; ảnh.
  - `tests/e2e/ho-tro.spec.ts` (mục 15): báo lỗi một thẻ rồi thấy nó trong hàng chờ của quản trị, bấm Nhận, Đã sửa; gửi một yêu cầu, trả lời trong quản trị, thấy câu trả lời ở trang Liên hệ; mở Trợ giúp; đánh dấu một bài ngữ pháp đã đọc rồi thấy dấu tích ở danh sách; axe sáng và tối; ảnh.
  - Thêm ba slug `bao-loi`, `ho-tro`, `tro-giup` vào danh sách trang của `tests/e2e/quan-tri.spec.ts`.
- [ ] **Đẩy đợt 4**, thông điệp commit có `[anh]`.

## 3. Mục tự kiểm và file test

| Mục | Nội dung | File |
| --- | --- | --- |
| 13 | Chấm phát âm | `PhatAmWavTest`, `PhatAmAzureTest`, `PhatAmApiTest`, `PhatAmHanMucTest`, `ChamPhatAm.test.tsx`, `tests/e2e/phat-am.spec.ts`; Azure thật: workflow `phat-am-thu` |
| 14 | Không lưu ghi âm, bản dự phòng | `PhatAmKhongLuuTest`, `PhatAmDuPhongTest`, `ChamPhatAm.test.tsx` |
| 15 | Báo lỗi, Liên hệ | `BaoLoiTest`, `BaoLoiQuanTriTest`, `LienHeTest`, `LienHeQuanTriTest`, `tests/e2e/ho-tro.spec.ts` |
| 6, 7 | Cổng ở chấm phát âm | `AiCongTest` |
| 8 | Dữ liệu gửi Azure | `AiDuLieuGuiTest`, `PhatAmAzureTest` |

Còn lại cho tuần 4: 5 lượt đọc thật trên điện thoại của người dùng (mục 13, cần khoá Azure); cảnh báo ở Tổng quan (mục 1, 5); mục 16, 18 đến 22.

## 4. Xong tuần 3 khi

1. Mọi ô ở mục 2 đã tích.
2. `python3 tools/ci/xem_ci.py` báo php và web đều đạt trên commit cuối của `work/g2-tuan3`.
3. `phat-am-thu` đã chạy và bốn kết luận đã ghi ở mục 5, hoặc ghi rõ đang chờ `AZURE_SPEECH_KEY`.
4. Không đụng `main`, không chạy deploy, không đọc hay ghi `.env`, không cầm giá trị khoá nào.
5. Báo người dùng tối đa 8 dòng: số test mới theo mục 13, 14, 15; kết quả `phat-am-thu` hoặc đang chờ khoá; việc cần người dùng làm (thêm hai secret Azure nếu muốn chấm điểm thật; thử micro trên điện thoại; đọc và sửa 12 câu hỏi thường gặp trong quản trị); phần để tuần 4; hỏi có đưa tuần 1 đến 3 lên `main` không.

## 5. Ghi chú

Mỗi quyết định tự chọn ngoài file này ghi một dòng ở đây: ngày, việc, chọn gì, vì sao.

- 29/11 (Fable, lúc soạn file): file ghi âm đi trong JSON dạng base64 để PHP không ghi file tạm; câu mẫu luôn lấy từ database; im lặng được nhận ra ở máy chủ nên không tốn giây của Azure; bản dự phòng không trừ lượt và không ghi `ai_requests`; trạng thái "tiếng ồn" lưu là `empty` vì cột `status` không có giá trị riêng; Câu hỏi thường gặp lưu JSON trong `pages.body` với mã ngôn ngữ trong slug; người gửi báo lỗi chưa được báo lại kết quả (để G3).
- 29/11 (Fable): người dùng đã chốt giao diện mặc định tiếng Anh (làm ở G3), đổi sang FSRS (G3), sao lưu database khi deploy (tuần 4). Không việc nào trong ba việc này thuộc tuần 3.

## 6. Mười hai câu hỏi thường gặp (chép vào `FaqMau::CAU`)

1. **Lernbuch là gì, dành cho ai?** Lernbuch là web học tiếng Đức từ A1 đến B2: từ vựng theo chủ đề, ngữ pháp, bài tập và bảng chia động từ. Bạn học được trên điện thoại hoặc máy tính.
2. **Học có mất tiền không?** Trong giai đoạn thử, mọi chức năng đang mở miễn phí. Khi có gói trả tiền, Lernbuch sẽ báo trước.
3. **Ôn tập hoạt động thế nào?** Mỗi lần bạn chấm một thẻ (Quên, Khó, Tốt, Dễ), Lernbuch hẹn ngày ôn lại thẻ đó: nhớ tốt thì hẹn xa hơn, quên thì ôn lại sớm. Trang Hôm nay cho biết hôm nay có bao nhiêu thẻ đến hạn.
4. **Mục tiêu ngày, chuỗi ngày và xu là gì?** Mục tiêu ngày là số thẻ bạn muốn học mỗi ngày, đổi được trong Cài đặt. Chuỗi ngày đếm số ngày bạn học liên tiếp. Xu được cộng khi bạn trả lời đúng. Cả ba chỉ để tạo động lực, không khoá chức năng nào.
5. **Có học được khi mất mạng không?** Có. Mở một chủ đề rồi bấm "Tải để học khi không có mạng". Khi có mạng lại, kết quả học tự được gửi lên.
6. **Cài Lernbuch lên màn hình điện thoại thế nào?** Trên Android, mở bằng Chrome, vào menu rồi chọn "Thêm vào màn hình chính". Trên iPhone, mở bằng Safari, bấm nút Chia sẻ rồi chọn "Thêm vào Màn hình chính".
7. **Vì sao máy tôi không đọc được câu ví dụ thành tiếng?** Từ được đọc bằng file âm thanh có sẵn. Câu ví dụ dùng giọng tiếng Đức của trình duyệt; máy chưa có giọng này thì thêm giọng Deutsch trong cài đặt giọng nói của máy, hoặc mở bằng Chrome hay Edge.
8. **AI trong Lernbuch làm được gì, ai dùng được?** AI giải thích từ, dịch, sửa ngữ pháp, trò chuyện, soạn bài học và chấm phát âm. Phần này đang thử nghiệm với một số tài khoản, chỉ dành cho người từ 18 tuổi đã đồng ý gửi nội dung cho AI, và mỗi ngày có số lượt giới hạn.
9. **AI có nhận thông tin cá nhân của tôi không?** Lernbuch chỉ gửi câu bạn gõ và ngữ cảnh của bài học, không gửi tên hay email. Đừng gõ thông tin riêng tư vào ô AI. Bạn rút đồng ý trong Cài đặt bất cứ lúc nào.
10. **Ghi âm khi chấm phát âm có bị lưu không?** Không. Bản ghi âm được gửi đi chấm rồi bỏ ngay; Lernbuch chỉ lưu điểm.
11. **Tôi thấy nội dung sai thì làm gì?** Bấm nút Báo lỗi (hình lá cờ) ngay trên thẻ, câu ví dụ, bài ngữ pháp, câu bài tập hoặc động từ.
12. **Tôi cần hỗ trợ, hoặc muốn xoá tài khoản và dữ liệu?** Gửi yêu cầu ở trang Liên hệ. Câu trả lời hiện ở đó và được gửi về email của bạn.
