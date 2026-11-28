# G2 tuần 1: nền AI (danh sách việc)

Nhánh `work/g2-tuan1`. Đọc `docs/huong-dan-lam-viec.md` trước. File này đủ để làm hết tuần 1: không cần mở kế hoạch G2.

**Xong tuần này thì có gì:** người học trong danh sách thử mở một thẻ từ, vào tab Hỏi AI, khai ngày sinh, đồng ý, bấm "Giải thích từ này" và nhận câu trả lời đi qua đủ tám chặng. CI dùng AI giả. Quản trị có trang Cài đặt AI và nút cho một người vào danh sách thử. Mục tự kiểm 1 đến 9 của kế hoạch G2 có test.

**Không làm tuần này:** chín chức năng AI còn lại và trang Hỏi AI; sửa prompt, thống kê, nhật ký AI trong admin; workflow `ai-thu`; đưa `GEMINI_API_KEY` vào deploy; chấm phát âm; cảnh báo ở trang Tổng quan; nút AI trong phiên học; đo hiệu năng; công cụ `tu_kiem_g2.py`.

## 1. Quyết định đã chốt

### 1.1 File mới

```
config/ai.php
app/Models/AiPrompt.php, AiRequest.php
app/Support/QuyenLoi.php                 quyền lợi theo gói (Billing sẽ dùng lại)
app/Support/Ai/CaiDatAi.php              cài đặt trong bảng settings
app/Support/Ai/SoanLoiNhac.php           ghép prompt, vô hiệu thẻ <input> giả
app/Support/Ai/KhuonAi.php               khuôn kết quả theo loại, kiểm, đổi ra JSON Schema
app/Support/Ai/BoNoi/                    BoNoiAi (interface), YeuCauAi, TraLoiAi, LoiBoNoi, BoNoiGemini, BoNoiOpenAi, BoNoiGia, ChonBoNoi
app/Support/Ai/DanhSachThu.php           danh sách thử (Pennant)
app/Support/Ai/DongYAi.php               bảng consents
app/Support/Ai/TrangThaiAi.php           chặng 1, 2 và khối "ai" của GET /toi
app/Support/Ai/HanMucAi.php              chặng 3
app/Support/Ai/BoNhoAi.php               chặng 4
app/Support/Ai/TranAi.php                chặng 5
app/Support/Ai/HoiAi.php                 đường ống tám chặng, ghi ai_requests
app/Support/Ai/TuChoiAi.php              ngoại lệ mang mã lỗi API
app/Http/Controllers/Api/V1/AiController.php
app/Console/Commands/AiDon.php           lệnh app:ai-don
app/Filament/Pages/TrangCaiDatAi.php
resources/js/app/lib/ai.ts
resources/js/app/components/ai/CongAi.tsx
resources/js/app/screens/ai/TabHoiAi.tsx
tests/chung/chen-lenh.json
tests/Feature/Ai*Test.php, QuyenLoiTest.php
tests/e2e/ai.spec.ts
```

Không có migration: mọi bảng đã có (`ai_prompts`, `ai_cache`, `ai_requests`, `consents`, `features`, `settings`, `subscriptions`, `plan_entitlements`, cột `users.birth_date`). Không thêm gói PHP hay npm.

### 1.2 Cài đặt

`config/ai.php`:

```php
return [
    'bo_noi' => env('AI_DRIVER'),   // fake | gemini | openai_compatible; trống thì theo ô Nhà cung cấp
    'cho_toi_da' => 20,             // giây cho cả lượt, tính cả lần thử lại
    'gemini' => ['khoa' => env('GEMINI_API_KEY'), 'goc' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta')],
    'openai' => ['khoa' => env('AI_OPENAI_API_KEY'), 'goc' => env('AI_OPENAI_BASE_URL')],
];
```

`ChonBoNoi::ten()`: lấy `config('ai.bo_noi')`, trống thì lấy ô `ai.provider`. Bộ nối thật mà thiếu khoá: ở production là lỗi cấu hình; ở nơi khác thì dùng `fake`. `fake` không bao giờ chạy ở production. Thêm `AI_DRIVER=fake` và `GEMINI_API_KEY=` vào `.env.example`, thêm `<env name="AI_DRIVER" value="fake"/>` vào `phpunit.xml`.

Bảng `settings` (đọc một câu `where name like 'ai.%'`, không nhớ tạm giữa các request; thiếu hoặc sai kiểu thì dùng mặc định):

| Ô trong trang Cài đặt AI | `settings.name` | Mặc định | Hợp lệ |
| --- | --- | --- | --- |
| Bật AI | `ai.enabled` | `false` | bool |
| Ai được dùng | `ai.audience` | `"testers"` | `testers`, `adults` |
| Nhà cung cấp | `ai.provider` | `"gemini"` (có trong seed) | `gemini`, `openai_compatible` |
| Model | `ai.default_model` | `"gemini-3.1-flash-lite"` (seed) | chữ ASCII, 1 đến 60 ký tự |
| Lượt mỗi phút | `ai.rpm_cap` | `10` | 1 đến 10.000 |
| Lượt mỗi ngày | `ai.rpd_cap` | `200` | 1 đến 1.000.000 |
| Lượt cùng lúc | `ai.max_concurrent` | `3` | 1 đến 10 |
| Chi phí mỗi ngày (USD) | `ai.daily_spend_cap_usd` | `20` (seed) | 0,1 đến 1.000 |
| Hạn mức giai đoạn thử | `ai.trial_daily_cap` | `50` | 0 đến 500 |
| Giá vào, giá ra (USD cho 1 triệu token) | `ai.price_usd_per_mtok` | `{"in":0.25,"out":1.5}` | 0 đến 1.000 |
| Tuổi tối thiểu | `ai.min_age` | `18` (seed) | chỉ hiện; mã luôn dùng `max(18, giá trị)` |

Lượt mỗi phút và mỗi ngày là số tạm: người dùng sẽ gửi hạn mức thật của Google, lúc đó họ tự sửa trong trang này.

### 1.3 Bộ nối

```php
interface BoNoiAi { public function goi(YeuCauAi $yc): TraLoiAi; }   // lỗi thì ném LoiBoNoi

final readonly class YeuCauAi {   // $khuon: JSON Schema hoặc null; $thamSo: chỉ BoNoiGia đọc
    public function __construct(public string $loai, public string $model, public string $heThong, public string $nguoiDung,
        public int $maxTokens, public float $nhietDo, public ?array $khuon, public float $choGiay, public array $thamSo = []) {}
}
final readonly class TraLoiAi { public function __construct(public string $chu, public int $tokenVao, public int $tokenRa, public int $tokenCache = 0) {} }
final class LoiBoNoi extends RuntimeException { const TAM_THOI = 'tam_thoi', CAU_HINH = 'cau_hinh', BI_CHAN = 'bi_chan'; public readonly string $loai; }
```

**Gemini** (đã kiểm ngày 28/11/2026 trên ai.google.dev/api/generate-content và trang structured output; không cần tra lại):

```
POST {goc}/models/{model}:generateContent
đầu:  x-goog-api-key: <khoá>        (không đưa khoá vào URL)
thân: { "systemInstruction": {"parts": [{"text": heThong}]},
        "contents": [{"role": "user", "parts": [{"text": nguoiDung}]}],
        "generationConfig": { "temperature": nhietDo, "maxOutputTokens": maxTokens,
            "responseFormat": {"text": {"mimeType": "APPLICATION_JSON", "schema": khuon}} } }   // khuon null thì bỏ "schema"
trả:  candidates[0].content.parts[*].text (ghép lại), candidates[0].finishReason, promptFeedback.blockReason,
      usageMetadata.{promptTokenCount, candidatesTokenCount, thoughtsTokenCount, cachedContentTokenCount}
lỗi:  {"error": {"code": 429, "status": "RESOURCE_EXHAUSTED", "message": "…"}}
```

- `tokenVao = promptTokenCount`, `tokenRa = candidatesTokenCount + thoughtsTokenCount`, `tokenCache = cachedContentTokenCount`; thiếu trường nào thì coi là 0.
- Không gửi `thinkingConfig`. Không dùng `responseSchema`, `_responseJsonSchema` (đã bị đánh dấu bỏ). Không gửi trường nào khác (không `labels`, không mã người dùng).
- TAM_THOI: 429, 500, 502, 503, 504, lỗi kết nối, hết giờ. CAU_HINH: 400, 401, 403, 404, thiếu khoá (không gọi HTTP). BI_CHAN: `promptFeedback.blockReason` có giá trị, không có candidate, hoặc `finishReason` là `SAFETY`, `RECITATION`, `BLOCKLIST`, `PROHIBITED_CONTENT`, `SPII`, `LANGUAGE`. `MAX_TOKENS` và lý do khác: trả chữ như thường, chặng 7 quyết định.
- Thông điệp của `LoiBoNoi` chỉ gồm mã HTTP và `error.status`. Không chép thân trả lời, không có khoá.
- `Http::withHeaders(...)->connectTimeout(5)->timeout($yc->choGiay)`; không dùng `retry()` của Laravel (đường ống tự thử lại).

**OpenAI tương thích:** `POST {goc}/chat/completions`, đầu `Authorization: Bearer <khoá>`, thân `{model, messages: [{role: system}, {role: user}], temperature, max_tokens, response_format: {type: "json_object"}}`. Đọc `choices[0].message.content`, `usage.prompt_tokens`, `usage.completion_tokens`; `finish_reason = content_filter` là BI_CHAN. Phân loại lỗi như Gemini.

**Giả (`BoNoiGia`):** `kich(array $kichBan)` xếp hàng các lần trả lời; mỗi phần tử là chuỗi, `TraLoiAi`, `Throwable` (ném ra) hoặc `Closure(YeuCauAi)`. Hết hàng thì trả mẫu theo loại. `$daNhan` giữ mọi `YeuCauAi` đã nhận; `datLai()` xoá sạch (gọi trong `beforeEach`). Mẫu của `word` đúng khuôn, có chứa `thamSo['word']`, `tokenVao = 1500`, `tokenRa = 500`. Ở production thì ném `LoiBoNoi(CAU_HINH)`.

### 1.4 Tám chặng (`HoiAi::hoi`)

```php
/** @param array<string, string> $thamSo  @return array{data: array<string, mixed>, meta: array<string, int|bool>} */
public static function hoi(User $user, string $loai, array $thamSo, string $trich, ?string $kieuNguCanh = null, ?int $maNguCanh = null): array
```

Không qua một chặng thì ném `TuChoiAi(mã, HTTP, phần thêm)`.

| # | Chặng | Không qua thì trả | HTTP | Dòng `ai_requests` |
| --- | --- | --- | --- | --- |
| 1 | Công tắc, danh sách thử | `ai_tam_tat`, `ai_chua_mo` | 503, 403 | không ghi |
| 2 | Tuổi, đồng ý | `ai_can_ngay_sinh`, `ai_chua_du_tuoi`, `ai_can_dong_y` | 403 | không ghi |
| 3 | Hạn mức người học | `ai_dang_xu_ly`, `ai_het_luot` (kèm `reset_at`, `limit`) | 429 | `quota`, chỉ khi hết lượt |
| 4 | Cache | trúng thì trả luôn | 200 | `ok`, `cache_hit = 1` |
| 5 | Trần hệ thống | `ai_qua_tai` | 503 | `blocked` |
| 6 | Gọi bộ nối | `ai_loi_tam_thoi`, `ai_loi_cau_hinh`, `ai_bi_tu_choi` | 503, 503, 422 | `error` |
| 7 | Kiểm khuôn | `ai_tra_loi_hong` | 502 | `error`, có token và chi phí |
| 8 | Trả lời | | 200 | `ok` |

- Chỉ dòng `status = ok` và `cache_hit = 0` mới trừ lượt.
- Chặng 3: lấy khoá `Cache::lock("ai:nguoi:{id}", 40)` không chờ; không lấy được thì `ai_dang_xu_ly`. Lấy được rồi mới đếm lượt. Nhả khoá trong `finally`.
- Chặng 5: kiểm lượt mỗi phút, lượt mỗi ngày, chi phí; rồi lấy một chỗ trong `ai:cho:1` … `ai:cho:N` (`Cache::lock`, 40 giây). Nhả trong `finally`, kể cả khi bộ nối ném lỗi.
- Chặng 6: đo thời gian bằng `now()` để test dùng `travel` được. Lần một chờ tối đa `cho_toi_da`. Lỗi TAM_THOI mà còn ít nhất 3 giây thì gọi lần hai với thời gian còn lại. Không có lần ba. CAU_HINH và BI_CHAN không thử lại.
- Prompt: dòng `ai_prompts` có `is_active = 1` và `version` lớn nhất của loại; không có thì `ai_loi_cau_hinh`. Model lấy từ ô `ai.default_model`; cột `ai_prompts.model` tuần này không dùng để gọi.
- `{level}`: trình độ tự chọn của người học; chưa chọn thì cấp của thẻ; không có thì `A1` (`NoiDung::CAP`).
- `SoanLoiNhac`: thay `{ten}` bằng `strtr` với đúng các tham số của loại (prompt hệ thống có ngoặc nhọn của JSON). Mọi giá trị tham số: bỏ ký tự điều khiển, rồi đổi dấu `<` đứng trước `input` hoặc `/input` (không kể hoa thường, có thể có dấu cách) thành `‹`.
- Chi phí: `cost_micro_usd = round(tokenVao × giá vào + tokenRa × giá ra)`. Giá tính cho 1 triệu token nên kết quả ra thẳng phần triệu USD: 1.500 vào, 500 ra, giá 0,25 và 1,5 cho 1.125.
- `input_excerpt`: tối đa 500 ký tự (`mb_substr`). Với `word` là mặt trước của thẻ. `latency_ms`: từ trước lần gọi một tới sau lần gọi cuối.
- `TuChoiAi` được đổi thành JSON ở `LoiApi::tuNgoaiLe`, controller không cần `try`. `LoiApi::json` thêm tham số thứ tư `$them` gộp vào thân (vd `reset_at`).

### 1.5 Khuôn của `word`

```php
// ['chu', tối đa ký tự, bắt buộc có chữ]   ['ds', tối đa phần tử, khuôn phần tử]
'word' => [
    'meaning' => ['chu', 600, true],
    'usage' => ['chu', 800, false],
    'examples' => ['ds', 5, ['de' => ['chu', 200, true], 'vi' => ['chu', 300, true]]],
    'common_mistakes' => ['ds', 5, ['chu', 300, true]],
],
```

`KhuonAi::doc($loai, $chu)` trả mảng sạch hoặc `null`. Luật: bỏ khoảng trắng hai đầu; bỏ một cặp rào ```` ```json ```` bọc ngoài nếu có; `json_decode` phải ra object. Thiếu khoá, sai kiểu, chuỗi dài quá, chuỗi bắt buộc mà rỗng, phần tử danh sách sai kiểu: hỏng. Danh sách dài quá thì cắt bớt. Khoá lạ thì bỏ. Chuỗi giữ nguyên, kể cả khi có thẻ HTML (app hiện nó như chữ). `KhuonAi::jsonSchema($loai)` chỉ dùng `type`, `properties`, `required`, `items`; không dùng giới hạn độ dài hay số phần tử (Google có thể từ chối khuôn phức tạp).

### 1.6 Hạn mức, cache, trần

- **Lượt đã dùng hôm nay:** số dòng `ai_requests` của người đó có `created_at >= LapLich::dauNgay(now, timezone của họ)`, `status = 'ok'`, `cache_hit = 0`, `kind <> 'pron'`. `reset_at = LapLich::dauNgayMai(...)`.
- **Hạn mức:** `QuyenLoi::so($user, 'ai_daily')`; khi `ai.audience = testers` thì lấy số nhỏ hơn giữa nó và `ai.trial_daily_cap`.
- **`QuyenLoi::so`:** gói của người dùng là các dòng `subscriptions` có `status` thuộc `trialing`, `active`, `starts_at <= now`, `ends_at` trống hoặc `> now`; nhiều gói thì lấy giá trị lớn nhất; không có gói thì dùng gói `free`. Ô `plans.open_access = true` thì lấy số lớn hơn giữa gói của mình và gói ở ô `plans.open_access_plan`. `-1` là không giới hạn.
- **Khoá cache:** `sha256(loại | version của prompt | model | JSON tham số đã chuẩn hoá)`. Chuẩn hoá: NFC, gộp khoảng trắng, bỏ khoảng trắng hai đầu, xếp khoá theo tên. **Giữ nguyên hoa thường**, vì tiếng Đức phân biệt `Essen` với `essen`.
- **Loại được lưu cache:** `lookup`, `word`, `doc`, `lesson`, `answer`, `sentence`. Không lưu `translate`, `grammar`, `chat`, `ask`.
- **Hạn cache:** `expires_at = now + 90 ngày`; `lesson` để trống. Trúng thì `hits + 1`, ghi `last_hit_at`. Mục hết hạn coi như không có và bị ghi đè khi lưu lại.
- **Lượt tới nhà cung cấp** (cho lượt mỗi phút, mỗi ngày): dòng `cache_hit = 0`, `status` thuộc `ok`, `error`, `kind <> 'pron'`. Mỗi phút: 60 giây gần nhất. Mỗi ngày và chi phí: tính từ nửa đêm `America/Los_Angeles`, vì Google đổi ngày lúc đó.
- **Trần chi phí:** tổng `cost_micro_usd` trong ngày `>= ai.daily_spend_cap_usd × 1.000.000` thì chặn.

### 1.7 Tuổi, đồng ý, danh sách thử

- `users.birth_date` là INT: 00:00 UTC của ngày sinh, có thể âm. Người học khai một lần; đã có thì từ chối. Nhân viên sửa được trong admin, có nhật ký.
- Đủ tuổi khi `ngày sinh + N năm <= hôm nay`, cả hai là ngày lịch theo múi giờ của người học, `N = max(18, ai.min_age)`. Cộng năm bằng `addYears()` mặc định của Carbon: sinh 29/2/2008 thì đủ 18 vào 1/3/2026.
- Đang đồng ý khi dòng mới nhất của `(user_id, kind = 'ai_processing')` có `granted = 1` và `document_version = DongYAi::PHIEN_BAN` (`'2026-11'`). Đồng ý hay rút đều thêm dòng mới, không sửa dòng cũ; `source = 'app'`, `ip` ghi như `NhatKy` (`inet_pton`).
- Danh sách thử: Pennant, tên `ai-thu-nghiem`, định nghĩa trong `AppServiceProvider::boot` trả `false`. Bật cho một người: `Feature::for($user)->activate('ai-thu-nghiem')`. Ô `ai.audience = adults` thì bỏ qua danh sách thử.
- Đổi nhà cung cấp hoặc chuyển sang bậc trả tiền thì phải sửa lời đồng ý và tăng `PHIEN_BAN`: ghi câu này vào README.

### 1.8 API

```
GET  /api/v1/toi                thêm khối "ai"
PUT  /api/v1/toi/ngay-sinh      {birth_date: "YYYY-MM-DD"} → {data: Toi}. Đã khai: 409 ngay_sinh_da_khai. Sai: 422
PUT  /api/v1/toi/dong-y-ai      {granted: bool, version: "2026-11"} → {data: Toi}. version khác bản hiện tại: 422
POST /api/v1/ai/giai-thich-tu   {card_id: int} → {data: {meaning, usage, examples: [{de, vi}], common_mistakes: []},
                                                  meta: {cached, limit, used, remaining, reset_at}}
```

Khối `ai` (tên trường và giá trị bằng tiếng Anh như phần còn lại của API):

```json
{"state": "hidden"}
{"state": "need_birth_date", "min_age": 18}
{"state": "underage", "min_age": 18}
{"state": "need_consent", "min_age": 18, "consent_version": "2026-11"}
{"state": "ready", "consent_version": "2026-11", "limit": 50, "used": 3, "reset_at": 1764262800}
```

`hidden`: công tắc tắt, hoặc người này ngoài danh sách thử; không trả thêm gì. Ngày sinh hợp lệ: đúng dạng, là ngày có thật, không ở tương lai, không trước 1900-01-01. `AiController::giaiThichTu` chỉ làm ba việc: kiểm `card_id`; tìm thẻ bằng `NoiDung::cacThe($user)` (không thấy: 404); gọi `HoiAi::hoi` với `word` = mặt trước của thẻ, `meaning` = nghĩa tiếng Việt (không có thì `?`).

Thêm vào `lang/vi/api.php`:

```php
'ai_tam_tat' => 'AI đang tạm tắt. Phần học vẫn dùng bình thường.',
'ai_chua_mo' => 'Tài khoản này chưa được mở AI.',
'ai_can_ngay_sinh' => 'Bạn cần khai ngày sinh trước khi dùng AI.',
'ai_chua_du_tuoi' => 'AI chỉ dành cho người từ 18 tuổi.',
'ai_can_dong_y' => 'Bạn cần đồng ý gửi nội dung cho AI trước khi dùng.',
'ai_het_luot' => 'Bạn đã dùng hết lượt AI hôm nay.',
'ai_dang_xu_ly' => 'Câu trước còn đang được trả lời, chờ một chút.',
'ai_qua_tai' => 'AI đang quá tải, thử lại sau ít phút.',
'ai_loi_tam_thoi' => 'AI chưa trả lời được, thử lại sau.',
'ai_loi_cau_hinh' => 'AI chưa trả lời được, thử lại sau.',
'ai_bi_tu_choi' => 'AI không trả lời được câu này.',
'ai_tra_loi_hong' => 'AI trả lời chưa đúng mẫu, thử lại.',
'ngay_sinh_da_khai' => 'Ngày sinh đã khai rồi. Cần sửa thì liên hệ hỗ trợ.',
```

### 1.9 Quản trị

- **`TrangCaiDatAi`**: slug `cai-dat-ai`, nhóm Hệ thống, quyền `ai.manage`. Các ô như bảng ở 1.2. Lưu bằng `CaiDatAi::luu`, ghi `NhatKy::ghi('setting.update', null, $truoc, $sau)` chỉ khi có đổi. Dưới form có một phần chỉ đọc: bộ nối đang chạy, khoá Gemini "đã có" hoặc "chưa có" (không bao giờ hiện giá trị), tuổi tối thiểu.
- **`ViewNguoiHoc`**: thêm ba dòng thông tin (Ngày sinh, Thử AI, Đồng ý AI) và ba nút: "Cho thử AI", "Bỏ thử AI" (quyền `ai.manage`, nhật ký `user.ai_thu`), "Sửa ngày sinh" (quyền `users.manage`, có ô lý do, nhật ký `user.ngay_sinh`). Ô ngày dùng `TextInput::make(...)->type('date')`.
- Lớp Filament đã dùng trong repo, chép `use` từ đó: `TextInput`, `Select`, `Toggle`, `Textarea`, `Section`, `Form`, `EmbeddedSchema`, `Actions`, `Action`, `TextEntry`, `Notification`.

### 1.10 App học

- `lib/api.ts`: `LoiApi` thêm `than: Record<string, unknown>` (toàn bộ thân JSON của lỗi) để đọc `reset_at`.
- `state/taiKhoan.tsx`: `Toi` thêm `ai: TrangThaiAi`; context thêm `nhanToi(toi: Toi)` để nhận hồ sơ mới từ ba API ở trên.
- `lib/ai.ts`: kiểu `TrangThaiAi` (union theo `state`), `GiaiThichTu`, `MetaAi`; ba hàm gọi API.
- `components/ai/CongAi.tsx`: theo `toi.ai.state`.
  - `hidden`: không vẽ gì.
  - `need_birth_date`: `fieldset` có ba `select` Ngày, Tháng, Năm (năm nay lùi về 1900) và nút Tiếp tục. Ngày không có thật (31/2) thì báo ngay, không gọi API.
  - `underage`: một đoạn chữ.
  - `need_consent`: tiêu đề, sáu ý, nút "Tôi đồng ý".
  - `ready`: vẽ `children`.
  - Đổi bước thì đưa tiêu điểm tới tiêu đề của bước mới.
- `screens/ai/TabHoiAi.tsx` (export mặc định, nạp bằng `lazy` + `Suspense`): nút "Giải thích từ này"; lúc chờ là `<p role="status">` và nút bị khoá; kết quả gồm bốn phần Nghĩa, Cách dùng, Ví dụ, Lỗi hay gặp (phần rỗng thì ẩn, chữ Đức có `lang="de"`); dòng số lượt còn lại; dòng nhắc AI có thể sai. Lỗi: `<p role="alert">` hiện `loi.message`; mất mạng hiện `ai.canMang`; `ai_het_luot` thêm giờ có lượt mới (đổi `reset_at` bằng `Intl.DateTimeFormat` với `timeZone` của tài khoản). Lỗi 403 loại `ai_can_*`, `ai_chua_*`, `ai_tam_tat` thì tải lại `/toi` để cổng hiện đúng bước. Kết quả vẽ bằng JSX thường; không dùng `dangerouslySetInnerHTML`.
- `screens/TheTu.tsx`: thêm tab `ai` (nhãn `ai.tab`) khi `state` là `need_birth_date`, `need_consent` hoặc `ready`.
- `screens/CaiDat.tsx`: mục AI khi `state` khác `hidden`. `ready`: câu đã đồng ý và nút "Rút đồng ý". Các trạng thái khác: một câu giải thích.
- Kiểu dáng: dùng lớp đang có. Nút phụ chép từ `LoiTai` trong `components/TaiNoiDung.tsx`; nút chính dùng `bg-tim text-on-tim hover:bg-tim-strong` như nút vào học ở `screens/HomNay.tsx`. Tiêu đề phần: `text-label font-semibold text-ink-2`.

Chữ thêm vào `vi.ts` (nhóm `ai`; sửa câu chữ được, giữ đủ ý của sáu ý đồng ý):

```ts
ai: {
    tab: 'Hỏi AI',
    ngaySinh: {
        tieuDe: 'Ngày sinh của bạn',
        moTa: 'AI chỉ dành cho người từ {tuoi} tuổi. Ngày sinh chỉ khai một lần; cần sửa thì liên hệ hỗ trợ.',
        ngay: 'Ngày', thang: 'Tháng', nam: 'Năm', tiepTuc: 'Tiếp tục',
        chonDu: 'Chọn đủ ngày, tháng, năm.', khongCoThat: 'Ngày này không có thật.',
    },
    chuaDuTuoi: 'AI chỉ dành cho người từ {tuoi} tuổi. Phần học còn lại bạn dùng bình thường.',
    dongY: {
        tieuDe: 'Trước khi dùng AI',
        y1: 'Nội dung bạn gõ và phần bài học liên quan được gửi tới Google (dịch vụ Gemini) để AI trả lời.',
        y2: 'Lernbuch đang dùng bậc miễn phí của Google: Google có thể dùng nội dung này để cải thiện sản phẩm của họ, và người của Google có thể đọc.',
        y3: 'Lernbuch không gửi tên, email hay mã tài khoản của bạn. Đừng gõ thông tin riêng tư như họ tên, số điện thoại, địa chỉ.',
        y4: 'Khi chấm phát âm, bản ghi âm được gửi tới Microsoft (Azure Speech) hoặc tới hãng làm trình duyệt để nhận giọng nói. Lernbuch không lưu bản ghi âm.',
        y5: 'AI có thể trả lời sai. Hãy đối chiếu với bài học.',
        y6: 'Bạn rút lại đồng ý bất cứ lúc nào trong Cài đặt.',
        nut: 'Tôi đồng ý',
    },
    giaiThich: 'Giải thích từ này',
    dangTraLoi: 'AI đang trả lời…',
    nghia: 'Nghĩa', cachDung: 'Cách dùng', viDu: 'Ví dụ', loiHayGap: 'Lỗi hay gặp',
    conLuot: 'Còn {n} lượt AI hôm nay.',
    coLuotMoiLuc: 'Có lượt mới lúc {gio}.',
    coTheSai: 'AI có thể sai. Thấy lạ thì đối chiếu với phần Nghĩa và ví dụ.',
    canMang: 'Cần có mạng để hỏi AI.',
    thuLai: 'Thử lại',
    caiDat: {
        tieuDe: 'AI', daDongY: 'Bạn đã đồng ý gửi nội dung cho AI.', rut: 'Rút đồng ý',
        chuaDongY: 'Bạn chưa đồng ý dùng AI. Mở tab Hỏi AI ở một thẻ từ để xem điều kiện.',
        chuaKhai: 'Bạn chưa khai ngày sinh. Mở tab Hỏi AI ở một thẻ từ để khai.',
    },
},
```

## 2. Việc cần làm

Làm theo thứ tự. Mỗi đợt một lần đẩy. Trong lúc CI của đợt trước chạy thì làm đợt sau. Đợt 4 chạy được test ở máy này, nên có thể làm xen vào lúc chờ CI của đợt 1 đến 3.

### Đợt 1: lõi gọi AI

- [x] **T1. Cấu hình.** `config/ai.php`, `.env.example`, `phpunit.xml`, `CaiDatAi` (`tatCa()`, `luu()`, hằng mặc định, kiểm giá trị như 1.2). File mẫu: `app/Support/Hoc/CaiDatHoc.php`.
- [x] **T2. Prompt.** `AiPrompt` (`dangDung($loai)`), `AiRequest` (`$timestamps = false`), `SoanLoiNhac`. Viết `tests/chung/chen-lenh.json`: 20 mẫu `{id, lang, kieu, text}`. Năm kiểu (đóng thẻ `</input>` giả, "bỏ qua hướng dẫn", đòi in prompt hệ thống, đổi vai, đòi trả lời ngoài JSON) bằng tiếng Việt, Đức, Anh là 15 mẫu; thêm 5 biến thể của đóng thẻ: `</INPUT>`, `< / input >`, thẻ lồng nhau, thẻ có thuộc tính, xuống dòng giữa thẻ. Đọc trước: `database/sql/seed.sql` dòng 179 đến 219.
  - `AiChenLenhTest`: mỗi mẫu ghép vào 8 loại prompt có `{text}`: lời nhắc người dùng có đúng một `<input>` và một `</input>`; phần giữa hai thẻ không còn chuỗi nào khớp `/<\s*\/?\s*input\b/i`; prompt hệ thống chỉ khác bản gốc ở `{level}`. Ngoặc nhọn JSON trong prompt hệ thống còn nguyên. Tham số không thuộc loại thì bị bỏ qua.
- [x] **T3. Ba bộ nối và `ChonBoNoi`.** Theo 1.3.
  - `AiBoNoiTest` (phần bộ nối; dùng `Http::fake` và `Http::preventStrayRequests()`): Gemini gửi đúng địa chỉ, đầu, thân, khoá không nằm trong URL; đọc đúng chữ và ba số token; 429, 500, 503, 504, lỗi kết nối là TAM_THOI; 400, 401, 403, 404, thiếu khoá là CAU_HINH (thiếu khoá thì không gọi HTTP); bị chặn an toàn là BI_CHAN; thông điệp lỗi không chứa khoá hay thân trả lời. OpenAI: đúng đường, đầu Bearer, đọc đúng, phân loại lỗi. `BoNoiGia` ném lỗi ở production. `ChonBoNoi`: `AI_DRIVER` thắng ô cài đặt; thiếu khoá ngoài production thì ra `fake`; ở production là CAU_HINH.
- [x] **T4. `KhuonAi`.** Theo 1.5.
  - `AiKhuonTest` (phần khuôn): sáu loại hỏng là chữ thường, JSON thiếu trường, sai kiểu, chuỗi quá dài, bị cắt giữa chừng, rỗng; thêm JSON là mảng, chuỗi bắt buộc rỗng. Chấp nhận: bọc rào ```` ```json ````, khoá lạ (bị bỏ), danh sách quá dài (bị cắt), chuỗi có thẻ HTML (giữ nguyên). `jsonSchema('word')` đúng dạng.
- [x] **T5. `QuyenLoi`.**
  - `QuyenLoiTest`: không có gói và tắt mở tạm thì 3; mở tạm thì 50; gói premium đang chạy thì 50; gói hết hạn hoặc chưa bắt đầu thì như không có; `trialing` được tính; gói `lifetime` (0) cùng mở tạm thì 50; `-1` thắng mọi số; ô `plans.open_access_plan` trỏ gói không có thì bỏ qua.
- [x] **Đẩy đợt 1.**

### Đợt 2: tám chặng và chức năng đầu tiên

- [x] **T6. Chặng 1 và 2.** `DanhSachThu`, `DongYAi`, `TrangThaiAi`; `ToiController` thêm `ngaySinh`, `dongYAi` và khối `ai`; hai đường mới trong `routes/api.php`; định nghĩa tính năng Pennant. File mẫu: `ToiController.php`, `LapLich::dauNgay`. Sửa các test đang so nguyên thân của `/toi`.
- [x] **T7. Chặng 3 đến 5.** `HanMucAi`, `BoNhoAi`, `TranAi` theo 1.6.
- [x] **T8. Đường ống và API.** `TuChoiAi`, `HoiAi`, `AiController`, đường `ai/giai-thich-tu`, `LoiApi` (tham số `$them`, nhận `TuChoiAi`), chữ lỗi ở `lang/vi/api.php`. File mẫu: `LoiApi.php`, `NoiDungController::the`.
- [x] **T9. Dọn dẹp.** Lệnh `app:ai-don`: xoá trắng `input_excerpt` của dòng quá 30 ngày, xoá mục `ai_cache` hết hạn. Chạy mỗi ngày qua `Schedule::command` trong `routes/console.php`.
- [x] **Test của đợt 2** (mỗi file là một mục tự kiểm; dòng đầu file ghi số mục):
  - `AiGiaiThichTuApiTest`: trả đúng khuôn và `meta`; nhật ký có `kind = word`, `context_type = card`, token, `cost_micro_usd = 1125`; thẻ không có, thẻ nháp, thẻ riêng của người khác thì 404 và không gọi AI; `card_id` thiếu hoặc là chữ thì 422; chưa đăng nhập thì 401; `{level}` theo trình độ tự chọn, rồi cấp của thẻ, rồi A1; bộ nối nhận đúng từ và nghĩa tiếng Việt.
  - `AiBoNoiTest` (phần đường ống, mục 1): lỗi tạm thời rồi thành công thì trả kết quả, gọi đúng 2 lần, trừ 1 lượt; lỗi tạm thời hai lần thì `ai_loi_tam_thoi`, gọi đúng 2 lần, không trừ lượt, nhật ký `error`; lần một mất 19 giây (`travel`) thì không thử lại; lỗi cấu hình thì gọi 1 lần; bị chặn thì `ai_bi_tu_choi`; thân trả về và nhật ký không chứa khoá.
  - `AiKhuonTest` (phần đường ống, mục 2): mỗi loại hỏng cho `ai_tra_loi_hong`, không lưu cache, không trừ lượt, nhật ký `error`.
  - `AiHanMucTest` (mục 4): dùng hết thì `ai_het_luot` kèm `reset_at` đúng đầu ngày mai của người đó, nhật ký `quota`, bộ nối không bị gọi; qua nửa đêm theo múi giờ người học thì có lượt mới; hai người ở `Asia/Ho_Chi_Minh` và `Europe/Berlin` có lượt mới ở hai giờ khác nhau; lượt lỗi, lượt sai khuôn, lượt cache không bị trừ; đang giữ khoá `ai:nguoi:{id}` thì `ai_dang_xu_ly`; còn 1 lượt mà gọi hai lần thì lần hai hết lượt; ô hạn mức giai đoạn thử nhỏ hơn thì dùng số nhỏ hơn; `adults` thì không dùng ô đó; dòng `kind = pron` không tính.
  - `AiTranTest` (mục 5): quá lượt mỗi phút thì `ai_qua_tai`, bộ nối không bị gọi, không trừ lượt, nhật ký `blocked`; 61 giây sau gọi được; quá lượt mỗi ngày thì chặn, qua nửa đêm `America/Los_Angeles` thì mở; chi phí chạm trần thì chặn; đã giữ đủ 3 chỗ thì chặn, nhả một chỗ thì gọi được; bộ nối ném lỗi vẫn nhả chỗ; lượt trúng cache không bị trần chặn.
  - `AiTuoiDongYTest` (mục 6): chưa khai thì `ai_can_ngay_sinh` và `state = need_birth_date`; khai hợp lệ lưu đúng INT; khai lần hai 409; ngày tương lai, trước 1900, sai dạng, 31/02 thì 422; thiếu một ngày mới đủ 18 thì `underage`; đúng sinh nhật 18 thì qua; sinh 29/2/2008: 28/2/2026 chưa đủ, 1/3/2026 đủ; đủ tuổi tính theo múi giờ người học (ở Việt Nam đã sang ngày mới, UTC chưa); chưa đồng ý thì `ai_can_dong_y`; đồng ý ghi một dòng `consents` đủ cột; rút thì khoá ngay và thêm dòng `granted = 0`; đồng ý lại thì mở; đồng ý bản cũ coi như chưa; `version` sai thì 422; ô `ai.min_age` đặt 16 vẫn dùng 18.
  - `AiDanhSachThuTest` (mục 7): công tắc tắt thì `ai_tam_tat` và `state = hidden` cho mọi người; bật và `testers`: ngoài danh sách `ai_chua_mo` và `hidden`, trong danh sách thì qua; `adults` thì ai đủ điều kiện cũng qua; tắt giữa hai lượt gọi thì lượt sau bị chặn, bật lại thì chạy; AI tắt hoặc bộ nối lỗi thì `GET /hom-nay` vẫn 200.
  - `AiDuLieuGuiTest` (mục 8): người học tên "Nguyễn Thị Rất Dễ Nhận", email `rat-de-nhan@example.com`: chữ bộ nối nhận (`BoNoiGia::$daNhan`, và thân gửi Gemini qua `Http::fake`) không chứa tên, email, `public_id`, địa chỉ IP; thân gửi Gemini chỉ có ba khoá `systemInstruction`, `contents`, `generationConfig`; `input_excerpt` dài tối đa 500 ký tự, không cắt giữa ký tự; `app:ai-don` xoá trắng đoạn trích quá 30 ngày và giữ dòng mới, xoá cache hết hạn và giữ `lesson`; lệnh có trong lịch.
  - `AiCacheTest` (mục 9): hỏi lại cùng thẻ thì bộ nối chỉ bị gọi 1 lần, `meta.cached = true`, không trừ lượt, `hits = 1`; người thứ hai cùng trình độ lấy từ cache; khác trình độ thì gọi lại; khoá giống nhau khi chỉ khác khoảng trắng hoặc dạng NFD, khác nhau khi khác hoa thường (`Essen`, `essen`); prompt lên version 2 hoặc đổi model thì không dùng bản cũ; sau 91 ngày thì gọi lại và ghi đè; kết quả hỏng không được lưu; bốn loại không lưu trả `false` ở `BoNhoAi::duocLuu`.
- [x] **Đẩy đợt 2.**

### Đợt 3: quản trị

- [x] **T10. `TrangCaiDatAi` và ba nút ở `ViewNguoiHoc`.** Theo 1.9. File mẫu: `TrangCaiDatHoc.php`, `ViewNguoiHoc.php`, `tests/Feature/CaiDatHocTest.php`, `tests/Feature/QuanTriNguoiHocTest.php`. Thêm trang mới vào danh sách trang trong `tests/e2e/quan-tri.spec.ts`.
  - `AiCaiDatTest`: người học và `content_editor` bị 403, `admin` vào được; lưu có hiệu lực ngay và ghi nhật ký trước, sau; lưu y như cũ không ghi thêm; giá trị sai (số âm, chữ, 0 lượt mỗi phút, model rỗng, nhà cung cấp lạ) báo lỗi đúng ô và không lưu; giá trị hỏng trong `settings` thì dùng mặc định; trang không chứa giá trị khoá (đặt `config('ai.gemini.khoa')` rồi kiểm HTML).
  - Thêm vào `AiDanhSachThuTest`: có `ai.manage` thì bật, tắt được và có nhật ký `user.ai_thu`; thiếu quyền thì không thấy nút và gọi hành động bị từ chối. Thêm vào `AiTuoiDongYTest`: có `users.manage` thì sửa được ngày sinh, có nhật ký `user.ngay_sinh`; thiếu quyền thì không.
- [x] **Đẩy đợt 3.**

### Đợt 4: app học

- [x] **T11. Giao diện.** Theo 1.10. File mẫu: `lib/api.ts`, `state/taiKhoan.tsx`, `screens/TheTu.tsx` (phần tab), `screens/CaiDat.tsx`, `screens/TheTu.test.tsx`, `test/fakeApi.ts`.
  - `CongAi.test.tsx`: năm trạng thái; ngày 31/2 không gọi API; khai xong thì sang bước đồng ý; kết quả `underage` thì không vẽ `children`; lời đồng ý có đủ sáu ý; bấm đồng ý gọi đúng API kèm `version`; đổi bước thì tiêu điểm nằm ở tiêu đề mới.
  - `TabHoiAi.test.tsx`: `hidden` thì thẻ từ không có tab Hỏi AI; bấm giải thích có dòng `status` rồi bốn phần; phần rỗng bị ẩn; chuỗi `<b>x</b><script>` hiện nguyên văn, không sinh phần tử; `ai_het_luot` hiện giờ có lượt mới; `ai_qua_tai`, `ai_loi_tam_thoi` hiện chữ của máy chủ và nút Thử lại; mất mạng hiện `ai.canMang`; `ai_can_dong_y` thì tải lại `/toi` và hiện bước đồng ý; số lượt còn lại lấy từ `meta`.
  - Thêm vào `CaiDat.test.tsx`: `hidden` không có mục AI; `ready` bấm Rút đồng ý gọi `PUT` với `granted: false` rồi hiện câu chưa đồng ý.
- [x] **T12. Test trình duyệt và hoàn tất.** `NguoiHocThuSeeder`: tăng `SO_TAI_KHOAN_HOC` lên 13; tài khoản 12 và 13 của mỗi cỡ màn hình nằm trong danh sách thử; mỗi lần chạy seeder thì xoá ngày sinh, dòng `consents`, dòng `ai_requests` của hai tài khoản đó; đặt `ai.enabled = true`, `ai.audience = "testers"`. Vẫn không làm gì ở production. Sửa `NguoiHocThuSeederTest`. File mẫu: `tests/e2e/noi-dung.spec.ts` (hàm `moThe`, `axe`, `ghiLoi`).
  - `tests/e2e/ai.spec.ts`: tài khoản 12 mở thẻ `das Brötchen`, vào tab Hỏi AI, khai ngày sinh người lớn, đồng ý, bấm giải thích, thấy kết quả của AI giả; tải lại trang và hỏi lại thì số lượt còn lại không giảm. Ở mỗi bước: axe sạch, không tràn ngang, không lỗi console, giao diện sáng và tối, `chupDu`. Tài khoản 13 khai ngày sinh dưới 18: thấy câu chưa đủ tuổi; tải lại thì tab Hỏi AI không còn.
  - README: thêm mục AI (biến `AI_DRIVER`, `GEMINI_API_KEY`; trang Cài đặt AI; lệnh `app:ai-don`; câu về phiên bản lời đồng ý ở 1.7).
- [ ] **Đẩy đợt 4**, thông điệp commit có `[anh]`.

## 3. Mục tự kiểm và file test

| Mục | Nội dung | File |
| --- | --- | --- |
| 1 | Nhà cung cấp lỗi | `tests/Feature/AiBoNoiTest.php` |
| 2 | Kết quả hỏng | `tests/Feature/AiKhuonTest.php`, `TabHoiAi.test.tsx` |
| 3 | Chèn lệnh, phần máy chủ | `tests/Feature/AiChenLenhTest.php`, `tests/chung/chen-lenh.json` |
| 4 | Hạn mức từng người | `tests/Feature/AiHanMucTest.php`, `tests/Feature/QuyenLoiTest.php` |
| 5 | Trần hệ thống | `tests/Feature/AiTranTest.php` |
| 6 | Tuổi và đồng ý | `tests/Feature/AiTuoiDongYTest.php`, `CongAi.test.tsx` |
| 7 | Danh sách thử, tắt khẩn cấp | `tests/Feature/AiDanhSachThuTest.php` |
| 8 | Dữ liệu gửi đi | `tests/Feature/AiDuLieuGuiTest.php` |
| 9 | Cache | `tests/Feature/AiCacheTest.php` |

Phần của các mục này để tuần sau: câu trả lời của model thật (mục 3, tuần 2), cảnh báo ở trang Tổng quan (mục 5, tuần 4), "cả 10 chức năng" (mục 6 và 7, tuần 2 và 3), xoá cuộc trò chuyện và dữ liệu gửi khi chấm phát âm (mục 8, tuần 2 và 3).

## 4. Xong tuần 1 khi

1. Mọi ô ở mục 2 đã tích.
2. `python3 tools/ci/xem_ci.py` báo php và web đều đạt trên commit cuối của `work/g2-tuan1`.
3. Không đụng `main`, không sửa workflow deploy, không đọc hay ghi `.env`.
4. Báo người dùng tối đa 8 dòng: số test mới theo từng mục 1 đến 9, phần để tuần sau, và hỏi có đưa lên `main` không. AI tắt sẵn trên máy chủ nên deploy không làm người học thấy gì mới.

## 5. Ghi chú

Mỗi quyết định tự chọn ngoài file này ghi một dòng ở đây: ngày, việc, chọn gì, vì sao.

- 28/11, T3: `BoNoiGia` là singleton trong container (`app(BoNoiGia::class)`), `kich`, `daNhan`, `datLai` là của đối tượng, không tĩnh: mỗi test có một bản mới, kịch bản của test này không lọt sang test khác.
- 28/11, T9: `HangDoiTest` so cột lịch bằng regex: thêm việc `app:ai-don` (lịch `17 3 * * *`) làm cột lịch của `schedule:list` được căn rộng hơn.
- 28/11, T10: hai ô chọn của trang Cài đặt AI dùng `select` gốc của trình duyệt: ô chọn kiểu Filament có nút xoá nhỏ, axe báo `target-size`.
- 28/11, T11: `i18n.test.ts` gom chữ ở mọi tầng (nhóm `ai` có tầng con `ai.ngaySinh`, `ai.dongY`, `ai.caiDat`).
- 28/11, T10: việc cho thử AI và sửa ngày sinh nằm ở `app/Support/QuanTri/AiNguoiHoc.php` (giống `KhoaTaiKhoan`), trang chỉ gọi.
