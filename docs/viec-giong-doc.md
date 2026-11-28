# Việc lẻ: đọc từ bằng cùng giọng với câu (danh sách việc cho Opus)

**TẠM HOÃN (29/11): người dùng bảo chưa cần chuyển. Không làm file này cho tới khi người dùng yêu cầu lại.**

Viết 29/11/2026. Nhánh `work/g2-tuan2` (đã có commit `bf8d1b5`: giọng trình duyệt ưu tiên giọng nữ). Đọc `docs/huong-dan-lam-viec.md` trước. File này đủ để làm; không cần dò lại code ngoài các file nêu ở mục 2.

**Người dùng yêu cầu (29/11):** nút nghe của **từ** đang phát file Piper giọng nam (thorsten), nút nghe của **câu ví dụ** đọc bằng giọng của trình duyệt (máy người dùng ra giọng nữ). Chuyển phần đọc từ sang đúng giọng đang đọc câu.

**Xong việc này thì có gì:** trên mỗi máy, từ và câu ví dụ đọc bằng cùng một giọng: giọng tiếng Đức của trình duyệt, ưu tiên giọng nữ. File Piper tạo sẵn không bị bỏ, chỉ lùi xuống làm dự phòng.

**Không làm trong việc này:** đổi `AUDIO_GIONG`; chạy workflow `audio` (kể cả `nghe-thu`); tạo lại file audio; sửa PHP, migration, `media_assets`; bỏ chức năng tải audio để học khi mất mạng; đưa lên `main`; deploy.

## 1. Quyết định đã chốt

### 1.1 Vì sao làm theo cách này

- Giọng đọc câu là giọng có sẵn trong trình duyệt của từng máy (Web Speech API), không phải file của web. Không xuất được giọng đó thành file để phát lại, nên cách duy nhất để từ có **đúng giọng đó** là cho từ cũng đọc bằng giọng trình duyệt.
- Điều này đảo thứ tự đã chốt ở G1 ("file Piper là chính, giọng trình duyệt là dự phòng") cho riêng phần đọc từ. Người dùng đã yêu cầu rõ; không hỏi lại.
- Hệ quả đã biết, chấp nhận: giọng khác nhau giữa các máy; máy chỉ có giọng Đức nam thì từ và câu cùng ra giọng nam; máy không có giọng Đức thì từ phát file Piper (hiện là giọng nam), câu báo nhẹ như cũ.

### 1.2 Thứ tự mới trong `nghe()` (`resources/js/app/lib/amThanh.ts`)

`nghe(chu, { url, cham })` giữ nguyên chữ ký và kiểu trả về (`SpeakResult`). Thứ tự mới:

1. Luôn `dungPhat()` trước (dừng file đang phát).
2. **Đi thẳng xuống file, không thử giọng trình duyệt**, khi có `url` và một trong hai điều sau đúng:
   - `canSpeak()` là `false`;
   - máy đang mất mạng (`navigator.onLine === false`) và giọng sẽ dùng là giọng qua mạng (`germanVoice()?.localService === false`). Lý do: giọng mạng của Chrome khi mất mạng có lúc im lặng mà không báo lỗi.
3. Ngoài ra: `r = await speak(chu, cham)`.
   - `ok` hoặc `interrupted`: trả `r`. **`interrupted` không được rơi xuống file** (người học vừa bấm nghe lại hoặc bấm nút khác).
   - `no-voice`, `unsupported`, `error`: có `url` thì `f = await phatFile(url, cham)`; `f` là `ok` hoặc `interrupted` thì trả `f`, còn lại trả `r` (để nút nghe hiện đúng lời nhắc của giọng trình duyệt, ví dụ cách thêm giọng Deutsch). Không có `url` thì trả `r`.
4. `phatFile()` và `TOC_DO_CHAM` giữ nguyên (file vẫn nghe chậm ở 0,65; giọng trình duyệt chậm ở 0,6 như `speech.ts`).

Không sửa `speech.ts` (đã ưu tiên giọng nữ ở `bf8d1b5`), trừ khi cần `export` thêm thứ đang dùng. Không sửa các chỗ gọi (`AudioButton.tsx`, `Hoc.tsx`, `PhienLuyen.tsx`, `hoc/chung.tsx`): chúng vẫn truyền `url` như cũ.

### 1.3 Chữ và tài liệu sửa theo

- Chú thích đầu file `amThanh.ts`, chú thích của `nghe()`, chú thích `AudioButton.tsx` dòng 18: ghi thứ tự mới.
- `docs/tao-audio.md` đoạn mở đầu: file Piper là dự phòng khi máy không có giọng Đức, giọng lỗi, hoặc mất mạng với giọng mạng. Giữ nguyên phần còn lại.
- `docs/huong-dan-lam-viec.md` mục bẫy đã gặp: thêm một dòng "đọc từ: giọng trình duyệt trước, file Piper sau; `interrupted` không rơi xuống file".
- Chuỗi `docTieng.*` trong `i18n/vi.ts` giữ nguyên.

## 2. Việc làm, theo thứ tự

- [ ] **T1. `amThanh.ts`.** Theo 1.2. `import { canSpeak, germanVoice, speak, type SpeakResult } from './speech'`.
- [ ] **T2. `amThanh.test.ts`.** Viết lại khối `describe('nghe: …')` cho thứ tự mới; đổi tên khối thành "giọng trình duyệt trước, file Piper dự phòng". Dùng `fakeSpeech()` và `AudioGia` có sẵn. Các ca bắt buộc:
  1. máy có giọng Đức, thẻ có file: đọc bằng giọng trình duyệt, `AudioGia.cacLan` rỗng; nghe chậm thì `rate` 0,6;
  2. máy có cả giọng nam và nữ: từ đọc bằng giọng nữ (cùng giọng với câu: gọi `nghe(tu, {url})` và `nghe(cau)` rồi so `voice.name` của hai lần đọc);
  3. máy không có giọng Đức, có file: phát file; nghe chậm thì `playbackRate` 0,65;
  4. trình duyệt không có `speechSynthesis`, có file: phát file, không ném lỗi;
  5. giọng trình duyệt lỗi thật (`synth.fail('synthesis-failed')`), có file: phát file;
  6. bấm nghe lần nữa khi đang đọc: lần trước trả `interrupted`, **không tạo `Audio`**;
  7. không giọng Đức, file 404: trả `no-voice`, không ném lỗi;
  8. không giọng Đức, không file: `no-voice`;
  9. mất mạng (`vi.stubGlobal('navigator', { onLine: false })` hoặc `vi.spyOn(navigator, 'onLine', 'get')`) và giọng `localService: false`: phát file ngay, `synth.spoken` rỗng; cùng lúc đó giọng `localService: true` thì vẫn đọc bằng giọng trình duyệt;
  10. file dự phòng đang phát rồi bấm nghe lại: file dừng, lần trước `interrupted`.
  `fakeSpeech` nhận thêm `localService` cho giọng (tham số tuỳ chọn, mặc định `true`); sửa trong `resources/js/test/fakeSpeech.ts`.
  Khối `describe('nút nghe với file audio')`: sửa hai ca cho khớp (ca "có file: bấm nghe chậm" nay đọc bằng giọng trình duyệt ở 0,6; thêm ca nút nghe khi máy không có giọng Đức thì phát file).
- [ ] **T3. Test trình duyệt.** `tests/e2e/che-do.spec.ts` đã dùng giọng giả có một giọng `de-DE` (`giaGiongDoc`), nên sau T1 mọi lần nghe từ sẽ vào `window.__daDoc`. Chạy lại file này; chỗ nào đang mong từ **không** được đọc bằng giọng trình duyệt thì sửa mong đợi. Thêm vào ca Flashcard: bấm P rồi E, hai lần đọc đều nằm trong `__daDoc` (từ và câu cùng một đường). `tests/e2e/hoc.spec.ts`: dò `audio` và `__daDoc`, sửa tương tự nếu có.
- [ ] **T4. Chú thích và tài liệu.** Theo 1.3.
- [ ] **T5. Chạy.** Từ gốc repo: `npx vitest run`, `npx tsc --noEmit -p tsconfig.json`, `npx eslint resources/js`. Đẩy `work/g2-tuan2`, xem CI bằng `python3 tools/ci/xem_ci.py --cho` (cần `web` đạt; `php` không đổi).

## 3. Xong khi

1. T1–T5 đánh dấu xong, CI xanh trên commit cuối.
2. Không file PHP, workflow, migration nào đổi (`git diff --stat` chỉ có `resources/js/`, `tests/e2e/`, `docs/`).
3. Báo người dùng tối đa 5 dòng: đã đổi gì; số test mới; **chưa lên web thật** (cần người dùng đồng ý đưa `work/g2-tuan2` lên `main`); nhắc việc còn chờ ở mục 4.

## 4. Việc còn chờ người dùng (không làm, chỉ nhắc)

- Máy không có giọng tiếng Đức vẫn nghe file Piper giọng **nam**. Muốn chỗ dự phòng này cũng là giọng nữ thì phải chọn một trong ba giọng nữ được phép bán (`de_DE-kerstin-low`, `de_DE-ramona-low`, `de_DE-eva_k-x_low`; file nghe thử ở nhánh `ci-results`, thư mục `audio-thu/`, run audio #5), tạo lại khoảng 12 nghìn file trên alwaysdata rồi đổi `AUDIO_GIONG`. Việc này chạm web thật: chỉ làm khi người dùng chọn giọng và đồng ý.
- Đưa `work/g2-tuan2` lên `main`: hỏi người dùng.

## 5. Ghi chú

(Opus ghi ở đây những chỗ phải tự quyết khi làm.)
