---
name: Lernbuch Online
description: Sổ tay khu vườn để người Việt học tiếng Đức
colors:
  paper: "oklch(97.5% 0.012 85)"
  sheet: "oklch(99.5% 0.005 85)"
  sunken: "oklch(95% 0.016 85)"
  line: "oklch(86% 0.02 80)"
  line-strong: "oklch(64% 0.025 75)"
  ink: "oklch(27% 0.025 60)"
  ink-2: "oklch(47% 0.025 60)"
  ink-3: "oklch(56% 0.022 60)"
  leaf: "oklch(50% 0.12 150)"
  leaf-strong: "oklch(44% 0.115 150)"
  leaf-tint: "oklch(94% 0.04 150)"
  on-leaf: "oklch(99.5% 0.005 150)"
  danger: "oklch(52% 0.17 28)"
  danger-tint: "oklch(95% 0.024 28)"
  warning-ink: "oklch(45% 0.09 70)"
  focus: "oklch(52% 0.15 255)"
  der: "oklch(50% 0.13 255)"
  die: "oklch(52% 0.16 15)"
  das: "oklch(50% 0.092 170)"
  plural: "oklch(52% 0.11 60)"
  level-0: "oklch(90% 0.01 80)"
  level-1: "oklch(86% 0.06 115)"
  level-2: "oklch(78% 0.09 130)"
  level-3: "oklch(68% 0.11 140)"
  level-4: "oklch(57% 0.12 148)"
  level-5: "oklch(46% 0.11 152)"
typography:
  display:
    fontFamily: "Baloo 2, Nunito, system-ui, sans-serif"
    fontSize: "2.5rem"
    fontWeight: 700
    lineHeight: 1.1
  headline:
    fontFamily: "Baloo 2, Nunito, system-ui, sans-serif"
    fontSize: "1.75rem"
    fontWeight: 600
    lineHeight: 1.2
  title:
    fontFamily: "Nunito, system-ui, sans-serif"
    fontSize: "1.25rem"
    fontWeight: 700
    lineHeight: 1.3
  body:
    fontFamily: "Nunito, system-ui, sans-serif"
    fontSize: "1rem"
    fontWeight: 400
    lineHeight: 1.55
  label:
    fontFamily: "Nunito, system-ui, sans-serif"
    fontSize: "0.875rem"
    fontWeight: 600
    lineHeight: 1.3
  study-word:
    fontFamily: "Nunito, system-ui, sans-serif"
    fontSize: "clamp(2rem, 7vw, 3.25rem)"
    fontWeight: 800
    lineHeight: 1.1
  handwritten:
    fontFamily: "Patrick Hand, Nunito, cursive"
    fontSize: "1.125rem"
    fontWeight: 400
    lineHeight: 1.4
rounded:
  sm: "6px"
  md: "10px"
  lg: "16px"
  pill: "999px"
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
    backgroundColor: "{colors.leaf}"
    textColor: "{colors.on-leaf}"
    typography: "{typography.title}"
    rounded: "{rounded.md}"
    padding: "12px 20px"
    height: "56px"
  button-primary-hover:
    backgroundColor: "{colors.leaf-strong}"
  button-secondary:
    backgroundColor: "{colors.sheet}"
    textColor: "{colors.ink}"
    typography: "{typography.body}"
    rounded: "{rounded.md}"
    padding: "12px 20px"
    height: "48px"
  button-secondary-hover:
    backgroundColor: "{colors.sunken}"
  grade-forgot:
    backgroundColor: "{colors.sheet}"
    textColor: "{colors.danger}"
    rounded: "{rounded.md}"
    height: "56px"
  grade-forgot-hover:
    backgroundColor: "{colors.danger-tint}"
  grade-hard:
    backgroundColor: "{colors.sheet}"
    textColor: "{colors.warning-ink}"
    rounded: "{rounded.md}"
    height: "56px"
  grade-good:
    backgroundColor: "{colors.leaf}"
    textColor: "{colors.on-leaf}"
    rounded: "{rounded.md}"
    height: "56px"
  grade-easy:
    backgroundColor: "{colors.sheet}"
    textColor: "{colors.leaf}"
    rounded: "{rounded.md}"
    height: "56px"
  grade-easy-hover:
    backgroundColor: "{colors.leaf-tint}"
  chip:
    backgroundColor: "{colors.sheet}"
    textColor: "{colors.ink-2}"
    typography: "{typography.label}"
    rounded: "{rounded.pill}"
    padding: "0 14px"
    height: "44px"
  chip-selected:
    backgroundColor: "{colors.leaf-tint}"
    textColor: "{colors.ink}"
  input:
    backgroundColor: "{colors.paper}"
    textColor: "{colors.ink}"
    typography: "{typography.body}"
    rounded: "{rounded.md}"
    padding: "8px 12px"
    height: "44px"
  tab:
    textColor: "{colors.ink-2}"
    typography: "{typography.label}"
    padding: "0 12px"
    height: "48px"
  tab-selected:
    textColor: "{colors.ink}"
  sheet:
    backgroundColor: "{colors.sheet}"
    textColor: "{colors.ink}"
    rounded: "{rounded.lg}"
    padding: "32px"
  nav-item:
    textColor: "{colors.ink-2}"
    typography: "{typography.label}"
    height: "64px"
  nav-item-active:
    textColor: "{colors.leaf}"
---

<!-- Ghi theo code 2026-11-26 (G0 ngày 7: Hôm nay, Học, Thẻ từ, Trang chủ đề, Cài đặt), gộp với bản nháp 2026-11-25. Nguồn chuẩn của token là design/tokens.css; tương phản kiểm bằng design/palette_check.py. Phần ghi "dự kiến" là quyết định đã chốt nhưng chưa có trong code. -->

# Design System: Lernbuch Online

## Overview

**Creative North Star: "Sổ tay khu vườn"**

Một cuốn sổ học tiếng Đức đặt trong khu vườn đổi theo mùa; mỗi từ là một cây con, ôn đều thì cây lớn. Tính cách: ấm, tỉ mỉ, khích lệ. Trên màn hình học, khu vườn chỉ góp màu, chữ, mật độ và một dấu ấn riêng; điều khiển, điều hướng và bố cục theo chuẩn mà người dùng đã quen.

App học và trang quản trị theo chế độ Operate: gọn, đều, trang trí đặt ở lúc nghỉ. Trang giới thiệu và bảng giá theo chế độ Persuade: khu vườn được làm chủ trang. Bài ngữ pháp và từ điển công khai theo chế độ Read: khu vườn ở khung, cột chữ yên tĩnh.

Dấu ấn là cây: 6 mức thuộc vẽ thành 6 giai đoạn hạt, mầm, lá, nụ, hoa, quả; lượng ôn mỗi ngày vẽ thành một mầm cao thấp trong dải 14 ngày; lúc một thẻ lên mức, hình cây của nó lớn lên. Chữ tiếng Đức luôn đi kèm mạo từ tô màu giống.

**Key Characteristics:**
- Giấy ấm, tờ sổ trắng ngà, mực nâu đậm, một màu lá cho hành động.
- 6 mức thuộc vẽ thành 6 giai đoạn của cây: hạt, mầm, lá, nụ, hoa, quả.
- Dải 14 ngày ở Hôm nay: mỗi ngày một mầm cây, cao theo số thẻ đã ôn.
- Cảnh mùa (xuân, hạ, thu, đông) chỉ ở đầu trang Hôm nay; phiên học trên nền giấy trơn.
- Từ tiếng Đức luôn là chữ lớn nhất trên màn hình học.

## Colors

Nền giấy ấm và mực nâu; màu chỉ dùng để chỉ hành động, trạng thái, giống của danh từ và mức thuộc.

### Primary
- **Lá non** (leaf): nút chính, mục đang chọn, ngày hôm nay, vạch dưới tab và mục điều hướng đang mở. **Lá đậm** (leaf-strong) khi di chuột; **lá nhạt** (leaf-tint) làm nền cho lựa chọn đang bật; chữ trên nền lá dùng **trắng lá** (on-leaf).

### Neutral
- **Giấy** (paper): nền trang và nền màn hình học.
- **Tờ sổ** (sheet): khung nội dung, nút phụ, nền nút chấm viền.
- **Khay** (sunken): nền rãnh của thanh tiến độ, ô bị che, di chuột trên nút phụ.
- **Mực** (ink): chữ chính. **Mực nhạt** (ink-2): chữ phụ, nhãn, chữ gợi ý trong ô nhập. **Mực phai** (ink-3): chỉ cho nét không phải chữ trên tờ sổ (thân mầm ngày đã qua, mũi tên chỉ tiếp), không dùng cho chữ trên nền giấy.
- **Đường kẻ** (line) và **đường kẻ đậm** (line-strong): dòng phân cách, viền; viền của ô nhập và nút phụ dùng đường kẻ đậm.

### Semantic
- **Gạch** (danger, nền danger-tint): lỗi và nút Quên. **Mật ong** (warning-ink): chữ và viền nút Khó, ngọn lửa chuỗi ngày, đồng xu, ngôi sao đã đánh dấu. **Focus**: vòng tiêu điểm 2 px.
- **Mạo từ**: der lam, die đỏ hồng, das xanh ngọc, số nhiều nâu cam (der, die, das, plural); chỉ tô chính mạo từ.
- **6 mức thuộc** (level-0 … level-5): xám giấy sang xanh lá đậm dần; tô ruột hình cây và dải màu của thanh 6 mức.
- **Mùa** (chỉ cho cảnh và minh hoạ): xuân oklch(82% 0.08 350), hạ oklch(86% 0.13 95), thu oklch(70% 0.14 55), đông oklch(84% 0.05 235).
- **Nền tối** ("đêm trong vườn"): mọi màu trên có cặp giá trị cho `data-theme="dark"` trong design/tokens.css; lá sáng lên (oklch(74% 0.13 150)) và chữ trên nút lá chuyển sang tối.

70/70 cặp màu đang dùng đạt WCAG 2 AA ở cả hai nền: chữ từ 4,5:1, nét và viền từ 3:1 (design/palette-check.json).

**The One Leaf Rule.** Màu lá chỉ dành cho hành động chính, trạng thái đúng và mục đang chọn.
**The Spoken Article Rule.** Màu der/die/das chỉ làm nổi chính mạo từ; người mù màu vẫn đọc được mạo từ.
**The Season Frame Rule.** Màu mùa không dùng cho nút, chữ hay trạng thái.

## Typography

**Display Font:** Baloo 2 (dự phòng Nunito)
**Body Font:** Nunito (dự phòng system-ui)
**Handwriting:** Patrick Hand, dùng ít
**Tiếng Trung:** phông hệ thống (PingFang SC, Microsoft YaHei, Noto Sans CJK SC)

**Character:** Baloo 2 tròn và vui cho tiêu đề thương hiệu; Nunito tròn, dễ đọc cho mọi chữ làm việc; Patrick Hand như ghi chú bút trong sổ. Cả ba tự lưu trữ (OFL), đủ dấu tiếng Việt và ä ö ü ß ẞ.

### Hierarchy
- **Display** (Baloo 2 700, 2.5rem, 1.1): tiêu đề trang giới thiệu. Thẻ từ dùng cỡ này cho chính từ, bằng Nunito 800.
- **Headline** (Baloo 2 600, 1.75rem, 1.2): lời chào Hôm nay, tên chủ đề, "Xong phiên", tên trang.
- **Title** (Nunito 700, 1.25rem, 1.3): tiêu đề mục, nghĩa tiếng Việt, chữ trên nút chính.
- **Body** (Nunito 400, 1rem, 1.55): nghĩa, câu ví dụ, nội dung; cột đọc 65–75 ký tự.
- **Label** (Nunito 600, 0.875rem, 1.3): nút nhỏ, tab, bộ lọc, nhãn, số liệu với chữ số đều nhau.
- **Study word** (Nunito 800, clamp(2rem, 7vw, 3.25rem)): từ đang học; vai trò duy nhất co giãn theo màn hình.
- **Handwritten** (Patrick Hand 400, 1.125rem): mẹo ghi tay trên thẻ từ, tối đa một khối mỗi thẻ.

Cỡ chữ người dùng chọn (nhỏ, vừa, lớn) nhân mọi cỡ với 0,9375 / 1 / 1,125. Tiêu đề xuống dòng cân (`text-wrap: balance`).

**The Working Type Rule.** Baloo 2 không dùng cho nút, nhãn, số liệu.

## Layout

Khung nội dung là tờ sổ rộng tối đa 1.120 px giữa màn hình; trên điện thoại tờ sổ tràn mép, không viền, không bóng. Thẻ từ rộng 720 px, cột học 640 px. Khoảng cách theo bước 4 px (4, 8, 12, 16, 24, 32, 48, 64): trong nhóm 8–12, giữa nhóm 24–32; lề trong tờ sổ 20 px trên điện thoại, 32 px trên máy tính.

Máy tính có thanh điều hướng trên cao 64 px; điện thoại có thanh dưới 5 mục. Phiên học chiếm cả màn hình, không thanh điều hướng: hàng trên mảnh (thoát, tiến độ, đếm thẻ), từ ở giữa, hành động sát đáy trong vùng ngón cái. Hành động chính của màn hình (Học ngay, Lật thẻ, Học tiếp) cao 56 px và chỉ có một; hành động phụ cao 48 px; mọi vùng bấm từ 44 px.

Hôm nay: dải 14 ngày ngang cả tờ sổ, dưới chia hai cột trên máy tính. Trang chủ đề: đầu trang chia trái thông tin, phải hành động; danh sách là bảng hai cột tiếng Đức, tiếng Việt.

## Elevation & Depth

Phẳng khi nghỉ; chỉ tờ sổ nổi nhẹ trên nền giấy. Bóng mềm có độ lệch và độ nhoè; nền tối dùng bóng đen đậm hơn.

### Shadow Vocabulary
- **Tờ sổ** (`box-shadow: 0 1px 2px oklch(27% 0.025 60 / 0.08), 0 4px 12px oklch(27% 0.025 60 / 0.06)`): khung nội dung trên máy tính.
- **Nổi** (`box-shadow: 0 2px 4px oklch(27% 0.025 60 / 0.10), 0 12px 28px oklch(27% 0.025 60 / 0.12)`): bảng mở, menu.

**The Soft Shadow Rule.** Không dùng bóng đổ phẳng không nhoè (kiểu `4px 4px 0`).

## Shapes

Góc bo 6 px cho ô bị che và phím gợi ý, 10 px cho nút, ô nhập, nút chấm, 16 px cho tờ sổ, tròn hẳn cho viên chọn, nút bật tắt và thanh tiến độ. Điều khiển giữ viền thẳng, nét 1 px; nút chấm viền 2 px. Viền vẽ tay và băng dính dự kiến cho minh hoạ, chưa dùng trong app học.

Hình cây vẽ bằng SVG trên lưới 24 px, nét mực nhạt 1,5 px, đầu nét tròn, ruột tô màu mức; mầm của dải ngày là thân 2 px với hai lá ở ngọn.

## Components

### Buttons
- **Shape:** bo 10 px.
- **Primary:** nền lá, chữ trắng lá (on-leaf) đậm, cao 56 px cho hành động chính của màn hình (Học ngay, Lật thẻ, Học tiếp); di chuột chuyển lá đậm trong 200 ms.
- **Secondary:** nền tờ sổ, viền đường kẻ đậm, chữ mực, cao 48 px; di chuột nền khay.
- **Text:** chữ lá, gạch chân khi di chuột (Hiện hết, Bỏ lọc).
- **Focus:** vòng 2 px màu focus, cách viền 2 px, cho mọi điều khiển.

### Nút chấm (Quên, Khó, Tốt, Dễ)
- Một hàng 4 nút cao 56 px sát đáy màn hình học, viền 2 px: Quên viền gạch, Khó viền mật ong, Tốt nền lá, Dễ viền lá. Mỗi nút ghi hạn ôn mới (10 phút, 1,5 ngày, 7 ngày…) và phím 1–4 (phím chỉ hiện từ 640 px).

### Chips
- **Style:** viên tròn cao 44 px, viền đường kẻ, chữ mực nhạt đậm vừa; nhóm chọn một là radio thật, phím mũi tên đi được.
- **State:** đang chọn có viền lá, nền lá nhạt, chữ mực; nút bật tắt (VI, EN, 中) cùng kiểu với `aria-pressed`.

### Cards / Containers
- **Corner Style:** 16 px (từ 768 px).
- **Background:** tờ sổ.
- **Shadow Strategy:** bóng Tờ sổ, viền đường kẻ; điện thoại tràn mép, không viền.
- **Internal Padding:** 20 px điện thoại, 32 px máy tính; không lồng thẻ trong thẻ, chia phần bằng đường kẻ.

### Inputs / Fields
- **Style:** nền giấy, viền đường kẻ đậm, bo 10 px, cao 44 px; ô tìm có kính lúp đầu ô và phím gợi ý "/" cuối ô; chữ gợi ý màu mực nhạt.
- **Focus:** vòng focus 2 px.
- **Error / Disabled:** lời báo màu gạch ngay dưới điều khiển, nói cách sửa; tắt thì giảm độ đậm, không đổi bố cục. Ô gõ tiếng Đức có hàng phím ä ö ü ß (dự kiến cho chế độ Gõ từ).

### Tabs
- Chữ nhãn đậm vừa, cao 48 px; tab đang mở chữ mực và vạch lá 2 px dưới chữ; phím mũi tên, Home, End chuyển tab.

### Navigation
- Máy tính: thanh trên nền giấy, viền dưới đường kẻ, tên Lernbuch bằng Baloo 2; mục có biểu tượng và chữ nhãn, mục đang mở chữ lá với vạch lá 2 px dưới.
- Điện thoại: thanh dưới cố định 5 cột, biểu tượng trên chữ, mục đang mở màu lá.

### Thanh 6 mức thuộc (signature)
- Dải màu cao 8 px chia theo số thẻ từng mức, dưới là 6 cột hình cây kèm số thẻ (tên giai đoạn hiện từ 1.280 px); không chỉ dựa vào màu. Ở trang chủ đề, mỗi cột là nút lọc (bấm lần nữa để bỏ lọc).

### Dải 14 ngày (signature)
- 10 ngày qua, hôm nay, 3 ngày tới; mỗi ô có mầm cây cao theo số thẻ, thứ viết tắt và ngày. Hôm nay mầm màu lá, ô đang chọn nền lá nhạt viền lá, ngày tới vẽ nét đứt; ngày không học là một chấm.

### Lúc cây lớn
- Thẻ lên mức thì hình cây mới nở từ 55% lên đủ cỡ trong 320 ms, đường cong ease-out; giảm chuyển động thì chỉ hiện dần. Tổng kết phiên là một câu và danh sách "Cây lớn lên" (từ, cây cũ, cây mới, tên mức).

## Do's and Don'ts

### Do:
- **Do** giữ từ tiếng Đức là chữ lớn nhất trên màn hình học.
- **Do** đi kèm chữ và biểu tượng với mọi màu trạng thái.
- **Do** đặt `lang="de"` và `hyphens: auto` cho chữ tiếng Đức.
- **Do** theo màu hệ thống cho chữ được chọn, con trỏ nhập, thanh cuộn, vòng focus.
- **Do** để tên đọc của nút bắt đầu bằng đúng chữ nhìn thấy trên nút.
- **Do** dùng 6 hình hạt, mầm, lá, nụ, hoa, quả chỉ để chỉ mức thuộc.

### Don't:
- **Don't** đặt cảnh mùa hay minh hoạ giữa phiên học.
- **Don't** dùng bóng đổ phẳng không nhoè, chữ gradient, kính mờ trang trí.
- **Don't** đặt nhãn nhỏ phía trên tiêu đề.
- **Don't** dùng emoji hay ký tự thay biểu tượng; biểu tượng vẽ bằng Lucide hoặc SVG riêng; OpenMoji chỉ là minh hoạ cho từ.
- **Don't** dùng ô số lớn kèm nhãn nhỏ kiểu bảng số liệu; số liệu nói bằng câu hoặc bằng cây.
- **Don't** chen bảng nâng cấp vào giữa phiên học.
