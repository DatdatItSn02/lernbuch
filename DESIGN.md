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
  leaf: "oklch(50% 0.12 150)"
  leaf-strong: "oklch(44% 0.115 150)"
  leaf-tint: "oklch(94% 0.04 150)"
  on-leaf: "oklch(99.5% 0.005 150)"
  danger: "oklch(52% 0.17 28)"
  danger-tint: "oklch(95% 0.024 28)"
  warning-ink: "oklch(45% 0.09 70)"
  focus: "oklch(52% 0.15 255)"
  article-der: "oklch(50% 0.13 255)"
  article-die: "oklch(52% 0.16 15)"
  article-das: "oklch(50% 0.092 170)"
  article-plural: "oklch(52% 0.11 60)"
typography:
  display:
    fontFamily: "Baloo 2, Nunito, sans-serif"
    fontSize: "2.5rem"
    fontWeight: 700
    lineHeight: 1.1
  headline:
    fontFamily: "Baloo 2, Nunito, sans-serif"
    fontSize: "1.75rem"
    fontWeight: 600
    lineHeight: 1.2
  title:
    fontFamily: "Nunito, sans-serif"
    fontSize: "1.25rem"
    fontWeight: 700
    lineHeight: 1.3
  body:
    fontFamily: "Nunito, sans-serif"
    fontSize: "1rem"
    fontWeight: 400
    lineHeight: 1.55
  label:
    fontFamily: "Nunito, sans-serif"
    fontSize: "0.875rem"
    fontWeight: 600
    lineHeight: 1.3
  study-word:
    fontFamily: "Nunito, sans-serif"
    fontSize: "clamp(2rem, 7vw, 3.25rem)"
    fontWeight: 800
    lineHeight: 1.1
  handwritten:
    fontFamily: "Patrick Hand, cursive"
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
    typography: "{typography.label}"
    rounded: "{rounded.md}"
    padding: "12px 20px"
    height: "44px"
  button-primary-hover:
    backgroundColor: "{colors.leaf-strong}"
  button-secondary:
    backgroundColor: "{colors.sheet}"
    textColor: "{colors.ink}"
    typography: "{typography.label}"
    rounded: "{rounded.md}"
    padding: "12px 20px"
    height: "44px"
  input:
    backgroundColor: "{colors.sunken}"
    textColor: "{colors.ink}"
    typography: "{typography.body}"
    rounded: "{rounded.sm}"
    padding: "10px 12px"
    height: "44px"
  card:
    backgroundColor: "{colors.sheet}"
    textColor: "{colors.ink}"
    rounded: "{rounded.lg}"
    padding: "16px"
  chip-filter:
    backgroundColor: "{colors.sunken}"
    textColor: "{colors.ink-2}"
    typography: "{typography.label}"
    rounded: "{rounded.pill}"
    padding: "6px 12px"
  chip-filter-selected:
    backgroundColor: "{colors.leaf-tint}"
    textColor: "{colors.ink}"
---

<!-- BẢN NHÁP 2026-11-25: đề xuất trước khi có code. Token màu đã kiểm tương phản bằng design/palette_check.py. Sau màn hình đầu tiên, chạy /impeccable document để ghi lại theo code thật. -->

# Design System: Lernbuch Online

## Overview

**Creative North Star: "Sổ tay khu vườn"**

Một cuốn sổ học tiếng Đức đặt trong khu vườn đổi theo mùa; mỗi từ là một cây con, ôn đều thì cây lớn. Tính cách: ấm, tỉ mỉ, khích lệ. Trên màn hình học, khu vườn chỉ góp màu, chữ, mật độ và một dấu ấn riêng; điều khiển, điều hướng và bố cục theo chuẩn mà người dùng đã quen.

App học và trang quản trị theo chế độ Operate: gọn, đều, trang trí đặt ở lúc nghỉ. Trang giới thiệu và bảng giá theo chế độ Persuade: khu vườn được làm chủ trang. Bài ngữ pháp và từ điển công khai theo chế độ Read: khu vườn ở khung, cột chữ yên tĩnh.

**Key Characteristics:**
- Giấy ấm, mực nâu đậm, một màu lá cho hành động.
- 6 mức thuộc vẽ thành 6 giai đoạn của cây: hạt, mầm, lá, nụ, hoa, quả.
- Cảnh mùa (xuân, hạ, thu, đông) chỉ ở đầu trang Hôm nay, trạng thái trống và lúc xong phiên.
- Từ tiếng Đức luôn là chữ lớn nhất trên màn hình học.

## Colors

Nền giấy ấm và mực nâu; màu chỉ dùng để chỉ hành động, trạng thái và giống của danh từ.

### Primary
- **Lá non** (oklch(50% 0.12 150)): nút chính, trạng thái đúng, mục đang chọn. Chữ trên nền lá đạt 5,6:1.

### Neutral
- **Giấy** (oklch(97.5% 0.012 85)): nền trang.
- **Tờ sổ** (oklch(99.5% 0.005 85)): khung nội dung, thẻ.
- **Khay** (oklch(95% 0.016 85)): thanh bên, ô nhập.
- **Mực** (oklch(27% 0.025 60)): chữ chính, 14,9:1 trên tờ sổ.
- **Mực nhạt** (oklch(47% 0.025 60)): chữ phụ, 6,8:1.
- **Đường kẻ** (oklch(86% 0.02 80)) và **đường kẻ đậm** (oklch(64% 0.025 75)): viền, đường kẻ; đường kẻ đậm cho viền cần đạt 3:1.

### Semantic
- **Gạch** (oklch(52% 0.17 28)): lỗi, chấm Quên. **Mật ong** (oklch(45% 0.09 70)): gần đúng, chấm Khó. **Focus** (oklch(52% 0.15 255)): vòng focus 2 px.
- **Mạo từ**: der oklch(50% 0.13 255), die oklch(52% 0.16 15), das oklch(50% 0.092 170), số nhiều oklch(52% 0.11 60); đều trên 5,5:1.
- **6 mức thuộc**: #e1ddd7, #cfd6aa, #a4c386, #73a967, #3e8a4d, #176938 (nền tối #3b3732 → #79cd91).
- **Mùa** (chỉ cho cảnh và minh hoạ): xuân #edafcb, hạ #ecd065, thu #e0843e, đông #acd1e7.
- **Nền tối** ("đêm trong vườn"): giấy oklch(19% 0.012 70), tờ sổ oklch(23% 0.014 70), mực oklch(93% 0.012 85), lá oklch(74% 0.13 150) với chữ tối trên nút. Giá trị đầy đủ ở design/tokens.css.

**The One Leaf Rule.** Màu lá chỉ dành cho hành động chính, trạng thái đúng và mục đang chọn.
**The Spoken Article Rule.** Màu der/die/das chỉ làm nổi chính mạo từ; người mù màu vẫn đọc được mạo từ.
**The Season Frame Rule.** Màu mùa không dùng cho nút, chữ hay trạng thái.

## Typography

**Display Font:** Baloo 2 (dự phòng Nunito)
**Body Font:** Nunito (dự phòng system-ui)
**Handwriting:** Patrick Hand, dùng ít

**Character:** Baloo 2 tròn và vui cho tiêu đề thương hiệu; Nunito tròn, dễ đọc cho mọi chữ làm việc; Patrick Hand như ghi chú bút trong sổ.

### Hierarchy
- **Display** (700, 2.5rem, 1.1): trang giới thiệu, lời chào Hôm nay, bảng tổng kết.
- **Headline** (600, 1.75rem, 1.2): tên trang, tên chủ đề.
- **Title** (Nunito 700, 1.25rem, 1.3): tiêu đề thẻ, mục.
- **Body** (Nunito 400, 1rem, 1.55): nghĩa, câu ví dụ, bài ngữ pháp; cột đọc 65–75 ký tự.
- **Label** (Nunito 600, 0.875rem, 1.3): nút, tab, bộ lọc, số liệu với chữ số đều nhau.
- **Study word** (Nunito 800, clamp(2rem, 7vw, 3.25rem)): từ đang học; vai trò duy nhất co giãn theo màn hình.

**The Working Type Rule.** Baloo 2 không dùng cho nút, nhãn, số liệu.

## Layout

Khung nội dung như tờ giấy, rộng tối đa 1.120 px, nằm giữa màn hình. Khoảng cách theo bước 4 px (4, 8, 12, 16, 24, 32, 48, 64): trong nhóm 8–12, giữa nhóm 24–32. Máy tính có thanh điều hướng trên; điện thoại có thanh dưới, nút chính ở nửa dưới màn hình, vùng bấm từ 44 px. Thẻ học rộng tối đa 640 px. Ô chủ đề là lưới co giãn theo khung chứa.

## Elevation & Depth

Phẳng khi nghỉ, nổi nhẹ khi tương tác. Bóng mềm có độ lệch và độ nhoè.

### Shadow Vocabulary
- **Tờ sổ** (`box-shadow: 0 1px 2px oklch(27% 0.025 60 / 0.08), 0 4px 12px oklch(27% 0.025 60 / 0.06)`): khung nội dung, thẻ.
- **Nổi** (`box-shadow: 0 2px 4px oklch(27% 0.025 60 / 0.10), 0 12px 28px oklch(27% 0.025 60 / 0.12)`): bảng mở, menu.

**The Soft Shadow Rule.** Không dùng bóng đổ phẳng không nhoè (kiểu `4px 4px 0`).

## Shapes

Góc bo 6 px cho ô nhập, 10 px cho nút, 16 px cho thẻ và tờ giấy, tròn hẳn cho chip. Viền vẽ tay và băng dính chỉ dùng cho ô chủ đề và minh hoạ; điều khiển giữ viền thẳng.

## Components

### Buttons
- **Shape:** bo 10 px, cao 44 px.
- **Primary:** nền lá non, chữ sáng; hover đậm hơn (lá đậm).
- **Hover / Focus:** đổi màu 120 ms; vòng focus 2 px màu focus, cách viền 2 px.
- **Secondary / Ghost:** nền tờ sổ có viền đường kẻ; nút ẩn chỉ có chữ.

### Chips
- **Style:** nền khay, chữ mực nhạt, bo tròn.
- **State:** đang chọn có nền lá nhạt và chữ mực.

### Cards / Containers
- **Corner Style:** 16 px.
- **Background:** tờ sổ.
- **Shadow Strategy:** bóng Tờ sổ.
- **Internal Padding:** 16 px; không lồng thẻ trong thẻ.

### Inputs / Fields
- **Style:** nền khay, bo 6 px, cao 44 px; ô gõ tiếng Đức có hàng phím ä ö ü ß và `lang="de"`.
- **Focus:** viền focus 2 px.
- **Error / Disabled:** viền gạch kèm lời nhắn nói cách sửa; tắt thì giảm độ đậm, không đổi bố cục.

### Navigation
- Thanh trên ở máy tính, thanh dưới ở điện thoại; mục đang mở có chữ mực và vạch lá dưới chữ.

### Thanh 6 mức thuộc (signature)
- Sáu đoạn theo màu 6 mức, mỗi đoạn có hình giai đoạn của cây và số thẻ; không chỉ dựa vào màu.

## Do's and Don'ts

### Do:
- **Do** giữ từ tiếng Đức là chữ lớn nhất trên màn hình học.
- **Do** đi kèm chữ và biểu tượng với mọi màu trạng thái.
- **Do** đặt `lang="de"` và `hyphens: auto` cho chữ tiếng Đức.
- **Do** theo màu hệ thống cho chữ được chọn, con trỏ nhập, thanh cuộn, vòng focus.

### Don't:
- **Don't** đặt cảnh mùa hay minh hoạ giữa phiên học.
- **Don't** dùng bóng đổ phẳng không nhoè, chữ gradient, kính mờ trang trí.
- **Don't** đặt nhãn nhỏ phía trên tiêu đề.
- **Don't** dùng emoji làm biểu tượng nút; OpenMoji chỉ là minh hoạ cho từ.
- **Don't** chen bảng nâng cấp vào giữa phiên học.
