---
name: Lernbuch Online
description: Vở ô li và hộp thẻ để người Việt học tiếng Đức
colors:
  paper: "oklch(98.4% 0.006 275)"
  sheet: "oklch(100% 0 0)"
  sunken: "oklch(95.6% 0.012 278)"
  line: "oklch(87.5% 0.03 278)"
  line-strong: "oklch(64% 0.05 280)"
  ink: "oklch(24% 0.035 285)"
  ink-2: "oklch(45% 0.04 282)"
  ink-3: "oklch(55% 0.035 282)"
  tim: "oklch(40% 0.17 285)"
  tim-strong: "oklch(33.5% 0.15 285)"
  tim-tint: "oklch(94.5% 0.026 285)"
  on-tim: "oklch(99.5% 0.002 285)"
  pen: "oklch(53% 0.2 27)"
  pen-tint: "oklch(95.5% 0.02 27)"
  box: "oklch(31% 0.11 285)"
  on-box: "oklch(96% 0.012 285)"
  box-pen: "oklch(53% 0.2 27)"
  danger: "oklch(50% 0.19 25)"
  danger-tint: "oklch(95.5% 0.02 25)"
  warning-ink: "oklch(45% 0.09 70)"
  focus: "oklch(52% 0.16 255)"
  der: "oklch(47% 0.15 255)"
  die: "oklch(48% 0.19 350)"
  das: "oklch(48% 0.1 165)"
  plural: "oklch(50% 0.11 60)"
  level-0: "oklch(92.5% 0.014 280)"
  level-1: "oklch(86% 0.045 283)"
  level-2: "oklch(77% 0.08 284)"
  level-3: "oklch(65% 0.12 285)"
  level-4: "oklch(52% 0.15 285)"
  level-5: "oklch(38% 0.16 286)"
typography:
  display:
    fontFamily: "Be Vietnam Pro, system-ui, sans-serif"
    fontSize: "2.5rem"
    fontWeight: 800
    lineHeight: 1.1
    letterSpacing: "-0.025em"
  headline:
    fontFamily: "Be Vietnam Pro, system-ui, sans-serif"
    fontSize: "1.625rem"
    fontWeight: 700
    lineHeight: 1.25
    letterSpacing: "-0.015em"
  title:
    fontFamily: "Be Vietnam Pro, system-ui, sans-serif"
    fontSize: "1.25rem"
    fontWeight: 700
    lineHeight: 1.3
  body:
    fontFamily: "Be Vietnam Pro, system-ui, sans-serif"
    fontSize: "1rem"
    fontWeight: 400
    lineHeight: 1.55
  label:
    fontFamily: "Be Vietnam Pro, system-ui, sans-serif"
    fontSize: "0.875rem"
    fontWeight: 600
    lineHeight: 1.3
  study-word:
    fontFamily: "Be Vietnam Pro, system-ui, sans-serif"
    fontSize: "clamp(2rem, 7vw, 3.25rem)"
    fontWeight: 800
    lineHeight: 1.1
    letterSpacing: "-0.02em"
  handwritten:
    fontFamily: "Patrick Hand, Be Vietnam Pro, cursive"
    fontSize: "1.125rem"
    fontWeight: 400
    lineHeight: 1.4
rounded:
  sm: "3px"
  md: "5px"
  lg: "8px"
spacing:
  "1": "4px"
  "2": "8px"
  "3": "12px"
  "4": "16px"
  "5": "24px"
  "6": "32px"
  "7": "48px"
  "8": "64px"
components:
  button-primary:
    backgroundColor: "{colors.tim}"
    textColor: "{colors.on-tim}"
    typography: "{typography.title}"
    rounded: "{rounded.md}"
    padding: "12px 20px"
    height: "56px"
  button-primary-hover:
    backgroundColor: "{colors.tim-strong}"
  button-secondary:
    backgroundColor: "{colors.sheet}"
    textColor: "{colors.tim}"
    typography: "{typography.label}"
    rounded: "{rounded.md}"
    padding: "0 16px"
    height: "44px"
  button-secondary-hover:
    backgroundColor: "{colors.tim-tint}"
  grade-forgot:
    backgroundColor: "{colors.sheet}"
    textColor: "{colors.danger}"
    rounded: "{rounded.md}"
    height: "56px"
  grade-hard:
    backgroundColor: "{colors.sheet}"
    textColor: "{colors.warning-ink}"
    rounded: "{rounded.md}"
    height: "56px"
  grade-good:
    backgroundColor: "{colors.tim}"
    textColor: "{colors.on-tim}"
    rounded: "{rounded.md}"
    height: "56px"
  grade-easy:
    backgroundColor: "{colors.sheet}"
    textColor: "{colors.tim}"
    rounded: "{rounded.md}"
    height: "56px"
  chip:
    backgroundColor: "{colors.sheet}"
    textColor: "{colors.ink-2}"
    typography: "{typography.label}"
    rounded: "{rounded.md}"
    padding: "0 14px"
    height: "44px"
  chip-selected:
    backgroundColor: "{colors.tim-tint}"
    textColor: "{colors.ink}"
  input:
    backgroundColor: "{colors.paper}"
    textColor: "{colors.ink}"
    typography: "{typography.body}"
    rounded: "{rounded.md}"
    padding: "8px 12px"
    height: "44px"
  top-bar:
    backgroundColor: "{colors.box}"
    textColor: "{colors.on-box}"
    typography: "{typography.label}"
    height: "56px"
  page:
    backgroundColor: "{colors.sheet}"
    textColor: "{colors.ink}"
    rounded: "{rounded.md}"
    padding: "32px"
  card-box:
    backgroundColor: "{colors.box}"
    textColor: "{colors.on-box}"
    rounded: "{rounded.md}"
    padding: "12px 10px 0"
  topic-card:
    backgroundColor: "{colors.sheet}"
    textColor: "{colors.ink}"
    rounded: "{rounded.md}"
    padding: "10px 16px 12px"
  study-card:
    backgroundColor: "{colors.sheet}"
    textColor: "{colors.ink}"
    rounded: "{rounded.md}"
    padding: "24px 20px 24px 56px"
  nav-item:
    textColor: "{colors.ink-2}"
    typography: "{typography.label}"
    height: "64px"
  nav-item-active:
    textColor: "{colors.tim}"
---

# Design System: Lernbuch Online

## Overview

**Creative North Star: "Vở ô li và hộp thẻ"**

Trang vở của học sinh Việt Nam (giấy trắng lạnh, đường lề đỏ, mực tím, bút đỏ thầy cô chấm) gặp chiếc hộp thẻ có ngăn của Leitner, nơi phương pháp ôn ngắt quãng bắt đầu. Mỗi từ là một tấm thẻ nằm trong một ngăn của hộp; ôn đúng thì thẻ sang ngăn sau, ngăn đánh dấu bằng 6 giai đoạn của cây. Tính cách: rõ, chăm, kiểu thầy cô chấm bài tận tình, không ồn ào. Thế giới này thay "Sổ tay khu vườn" ngày 26/11/2026, vì bản cũ (nền kem, xanh lá, chữ tròn, minh hoạ đồi) trông như mọi app học làm bằng AI.

App học và trang quản trị theo chế độ Operate: điều hướng, nút, bảng theo chuẩn người dùng đã quen; thế giới chỉ góp màu, chữ, mật độ và vài dấu ấn. Trang giới thiệu (Persuade) và bài đọc (Read) chưa làm; khi làm thì dùng cùng giấy, mực và bút đỏ.

**Key Characteristics:**
- Giấy trắng hơi lạnh, chữ mực đậm ngả tím, một màu mực tím cho hành động và mức thuộc.
- Bút đỏ chỉ để chấm: tích ngày đã học, khoanh hôm nay, số thẻ đến hạn, dấu đúng ở lề thẻ.
- Đường lề đỏ nhạt ở đầu Hôm nay, trên thẻ học và trang chào, như lề vở ô li.
- Hộp thẻ 6 ngăn ở Hôm nay; thẻ học có tai màu theo mức và hai thẻ chờ phía sau.
- Cây 6 giai đoạn vẽ nét mực, luôn có tên mức đi kèm.
- Từ tiếng Đức luôn là chữ lớn nhất trên màn hình học.

## Colors

Giấy trắng lạnh, mực đậm; mực tím cho hành động và mức thuộc, bút đỏ cho dấu chấm, còn lại là màu của mạo từ và trạng thái.

### Primary
- **Mực tím** (tim): nút chính, mục đang chọn, liên kết hành động, nét vẽ cây. **Mực tím đậm** (tim-strong) khi di chuột; **tím nhạt** (tim-tint) làm nền lựa chọn đang bật và nét dạ quang dưới chữ ở câu Hôm nay; chữ trên nền mực tím dùng **trắng giấy** (on-tim).
- **Hộp** (box, chữ on-box): thanh trên cùng, lòng hộp thẻ, dải đầu trang chào; số đến hạn trong hộp viết bằng **bút đỏ trên hộp** (box-pen) trên ô trắng.

### Neutral
- **Giấy** (paper): nền trang và nền màn hình học.
- **Trang vở** (sheet): khung nội dung, thẻ học, thẻ chủ đề, nút phụ.
- **Khay** (sunken): rãnh thanh tiến độ, di chuột trên nút phụ.
- **Mực** (ink): chữ chính. **Mực nhạt** (ink-2): chữ phụ, nhãn. **Mực phai** (ink-3): chỉ cho nét không phải chữ (mũi tên chỉ tiếp).
- **Dòng kẻ** (line) và **dòng kẻ đậm** (line-strong): viền, ô ngày, phân cách; viền ô nhập và gạch đứt trên thẻ học dùng dòng kẻ đậm.

### Semantic
- **Bút đỏ** (pen, nền pen-tint): dấu chấm và đường lề (lề vẽ ở độ đậm 45%). **Gạch** (danger): lỗi và nút Quên. **Mật ong** (warning-ink): nút Khó, đồng xu, ngôi sao đã đánh dấu. **Focus**: vòng tiêu điểm 2 px.
- **Mạo từ**: der lam, die đỏ tía (khác hẳn bút đỏ), das xanh ngọc, số nhiều nâu cam; chỉ tô chính mạo từ.
- **6 mức thuộc** (level-0 … level-5): mực tím từ nhạt đến đậm (bản tối ngược lại); chữ trên từng nền dùng on-level-0 … on-level-5.
- **Mùa** (chỉ cho cành vẽ nét mực cạnh ngày ở Hôm nay): xuân, hạ, thu, đông theo Cài đặt.
- **Nền tối** ("mực đêm"): mọi màu có cặp giá trị cho `data-theme="dark"` trong design/tokens.css.

47/47 cặp màu đang dùng đạt WCAG 2 AA ở cả hai nền: chữ từ 4,5:1, nét và viền từ 3:1 (design/palette-check.json).

**The One Ink Rule.** Mực tím chỉ dành cho hành động chính, mục đang chọn và mức thuộc.
**The Red Pen Rule.** Bút đỏ chỉ để chấm (tích, khoanh, số đến hạn, lề); không dùng cho nút, chữ thường hay lỗi.
**The Spoken Article Rule.** Màu der/die/das chỉ làm nổi chính mạo từ; người mù màu vẫn đọc được mạo từ.
**The Season Sprig Rule.** Màu mùa chỉ tô cành nhỏ cạnh ngày; không dùng cho nút, chữ hay trạng thái.

## Typography

**Font:** Be Vietnam Pro cho mọi chữ, kể cả tiêu đề (thiết kế cho tiếng Việt, đủ ä ö ü ß ẞ)
**Handwriting:** Patrick Hand, chỉ cho ghi chú của người học
**Tiếng Trung:** phông hệ thống (PingFang SC, Microsoft YaHei, Noto Sans CJK SC)

**Character:** một họ chữ không chân, nét thẳng, đậm nhạt rõ; tiêu đề phân biệt bằng độ đậm 700–800 và khoảng chữ hơi khít, không cần font thứ hai. Tự lưu trữ (OFL) từ @fontsource, chỉ ba bộ chữ latin, latin-ext, vietnamese.

### Hierarchy
- **Display** (800, 2.5rem, 1.1, khít −0,025em): tên Lernbuch ở trang chào.
- **Headline** (700, 1.625rem, 1.25): câu việc hôm nay, tên chủ đề, tên trang, "Xong phiên".
- **Title** (700, 1.25rem, 1.3): tiêu đề mục, nghĩa tiếng Việt, chữ trên nút chính.
- **Body** (400, 1rem, 1.55): nghĩa, câu ví dụ; cột đọc 65–75 ký tự.
- **Label** (600, 0.875rem, 1.3): nút nhỏ, tab, nhãn, số liệu với chữ số đều nhau.
- **Study word** (800, clamp(2rem, 7vw, 3.25rem), khít −0,02em): từ đang học.
- **Handwritten** (Patrick Hand 400, 1.125rem): ghi chú của người học, ẞ hiện bằng Be Vietnam Pro.

Cỡ chữ người dùng chọn (nhỏ, vừa, lớn) nhân mọi cỡ với 0,9375 / 1 / 1,125. Tiêu đề `text-wrap: balance`, đoạn văn `text-wrap: pretty`.

## Layout

Khung nội dung là trang vở rộng tối đa 1.120 px; điện thoại tràn mép. Khoảng cách theo bước 4 px; lề trong 20 px điện thoại, 32 px máy tính. Đường lề đỏ cách mép 40 px (48 px máy tính), nội dung bắt đầu ở 56 px (64 px); cột lề để dấu chấm hoặc hình vẽ.

Thanh trên cùng màu hộp cao 48 px (56 px máy tính, có điều hướng); điện thoại có thêm thanh dưới 5 mục. Phiên học chiếm cả màn hình: hàng trên mảnh, thẻ học ở trên, hành động sát đáy. Một hành động chính 56 px mỗi màn hình; vùng bấm từ 44 px.

Hôm nay: điện thoại một cột (câu, hộp thẻ, Học ngay, mục tiêu, số từ mới, 14 ngày, chủ đề, chế độ học); máy tính hai cột, trái là việc hôm nay, phải là 14 ngày và chủ đề.

## Elevation & Depth

Phẳng; ranh giới bằng dòng kẻ mảnh. Bóng chỉ cho thẻ học (nổi trên chồng thẻ) và bảng mở.

### Shadow Vocabulary
- **Thẻ** (`box-shadow: 0 1px 2px oklch(30% 0.06 285 / 0.10), 0 3px 8px oklch(30% 0.06 285 / 0.06)`): thẻ học.
- **Nổi** (`box-shadow: 0 2px 4px oklch(30% 0.06 285 / 0.12), 0 10px 24px oklch(30% 0.06 285 / 0.14)`): bảng mở, menu.

**The Thin Line Rule.** Trang, thẻ chủ đề, ô ngày dùng viền 1 px, không bóng; không bóng đổ phẳng không nhoè.

## Shapes

Góc gần vuông như giấy cắt: 3 px cho vạch tiến độ và nhãn nhỏ, 5 px cho nút, ô nhập, ô chọn, thẻ, 8 px tối đa. Không viên thuốc tròn hẳn. Dấu bút đỏ vẽ tay bằng SVG (tích hơi nghiêng, vòng khoanh hở đầu nét). Cây vẽ nét 1,5 px đầu tròn trên lưới 24 px, không tô ruột.

## Components

### Buttons
- **Primary:** nền mực tím, chữ trắng giấy đậm, cao 56 px (Học ngay, Lật thẻ, Học tiếp); di chuột mực tím đậm trong 200 ms.
- **Secondary:** nền trang vở, viền mực tím hoặc dòng kẻ đậm, chữ mực tím, cao 44–48 px; di chuột nền tím nhạt.
- **Focus:** vòng 2 px màu focus cách viền 2 px; trên thanh hộp dùng màu chữ on-box.

### Nút chấm (Quên, Khó, Tốt, Dễ)
- Một hàng 4 nút cao 56 px sát đáy, viền 1,5 px: Quên chữ gạch, Khó chữ mật ong, Tốt nền mực tím, Dễ viền mực tím; mỗi nút ghi hạn ôn mới và phím 1–4.

### Chips
- Ô vuông bo 5 px cao 44 px, viền dòng kẻ; đang chọn viền mực tím, nền tím nhạt. Nhóm chọn một là radio thật.

### Hộp thẻ (signature, Hôm nay)
- Lòng hộp màu hộp, 6 ngăn từ Hạt đến Quả; chồng thẻ màu on-box cao theo số từ, hai vạch mảnh ở đầu chồng cho thấy mép thẻ; tai thẻ màu mức kèm hình cây và tên; số đến hạn (ngăn Hạt: "+số từ mới sắp học") trên ô trắng chữ bút đỏ; dưới mỗi ngăn là tổng số thẻ. Trình đọc màn hình nghe một câu tóm tắt.

### Thẻ học (signature, phiên học)
- Thẻ trang vở bo 5 px, bóng Thẻ, hai thẻ chờ lộ mép phía trên; tai thẻ góc phải màu mức kèm hình cây và tên mức; đường lề đỏ trong thẻ, dấu tích bút đỏ ở lề khi xem lại thẻ vừa chấm Tốt hoặc Dễ; đáp án hiện tiếp dưới gạch đứt.

### Thẻ chủ đề
- Viền dòng kẻ, dòng tên chủ đề có vạch bút đỏ bên dưới (như dòng đầu thẻ Karteikarte), rồi câu "5 đến hạn · 9 từ mới · 2 đã thành quả" (số đến hạn chữ bút đỏ) và thanh 6 mức.

### Dải 14 ngày
- Hàng ô kẻ như ô vở: thứ, dấu tích bút đỏ nếu có học, ngày; hôm nay khoanh bút đỏ; 3 ngày tới ghi số thẻ sẽ đến hạn, ngày nhạt hơn. Là một tablist: chọn ngày thì câu bên dưới đổi.

### Thanh 6 mức thuộc
- Dải 8 px mực tím nhạt đến đậm theo tỉ lệ; bản đầy đủ có hàng "hình cây, tên mức, số"; ở trang chủ đề mỗi mức là nút lọc. Không chỉ dựa vào màu.

### Navigation
- Máy tính: thanh hộp, tên Lernbuch đậm 800, mục có biểu tượng và chữ; mục đang mở có vạch on-box 3 px dưới. Điện thoại: thanh dưới nền trang vở 5 cột, mục đang mở chữ mực tím với vạch 3 px ở mép trên.

### Lúc cây lớn
- Thẻ lên mức thì hình cây mới nở từ 55% lên đủ cỡ trong 320 ms, ease-out; giảm chuyển động thì chỉ hiện dần.

## Do's and Don'ts

### Do:
- **Do** giữ từ tiếng Đức là chữ lớn nhất trên màn hình học.
- **Do** nói số liệu bằng câu ("Hôm nay có 12 thẻ cần ôn và 9 từ mới") và kèm tên mức bên cạnh hình cây.
- **Do** đi kèm chữ và biểu tượng với mọi màu trạng thái.
- **Do** đặt `lang="de"` cho chữ tiếng Đức.
- **Do** để tên đọc của nút bắt đầu bằng đúng chữ nhìn thấy trên nút.
- **Do** chỉ hiện chức năng đã có; chức năng sắp có ghi một câu, không vẽ ô giả.

### Don't:
- **Don't** dùng nền kem, giấy ngả vàng hay be làm nền.
- **Don't** dùng chữ tròn kiểu app thiếu nhi, nút viên thuốc, bo góc lớn.
- **Don't** vẽ minh hoạ cảnh (đồi, cây tròn, mặt trời) hay hình nền sọc lặp.
- **Don't** dùng bút đỏ cho nút hay lỗi; lỗi dùng màu gạch kèm chữ.
- **Don't** đặt nhãn nhỏ phía trên tiêu đề, lời chào chung chung ở đầu trang.
- **Don't** dùng emoji thay biểu tượng; dùng Lucide hoặc SVG riêng.
- **Don't** dùng ô số lớn kèm nhãn nhỏ kiểu bảng số liệu.
- **Don't** chen bảng nâng cấp vào giữa phiên học.
