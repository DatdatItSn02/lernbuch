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
  chinese:
    fontFamily: "PingFang SC, Microsoft YaHei, Noto Sans CJK SC, sans-serif"
    fontSize: "1rem"
    fontWeight: 400
    lineHeight: 1.55
  mono:
    fontFamily: "ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace"
    fontSize: "1.25rem"
    fontWeight: 400
    lineHeight: 1.3
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

<!-- impeccable:design-map -->
Name: Lernbuch Online
Colors: primary: tim oklch(40% 0.17 285); neutral: paper oklch(98.4% 0.006 275); semantic: pen oklch(53% 0.2 27); primary: tim-strong oklch(33.5% 0.15 285); primary: tim-tint oklch(94.5% 0.026 285) (+26 more in the sidecar)
Fonts: Be Vietnam Pro (display, headline, title, body, label, study-word); Patrick Hand (handwritten); PingFang SC (chinese, nghĩa tiếng Trung); ui-monospace (mono, ô gợi ý chữ cái, mã trong trang quản trị)
Spacing: 1 4px, 2 8px, 3 12px, 4 16px, 5 24px, 6 32px, 7 48px, 8 64px
Radius: sm 3px, md 5px, lg 8px
North Star: "Vở ô li và hộp thẻ"
Rules:
- The One Ink Rule. Mực tím chỉ dành cho hành động chính, mục đang chọn và mức thuộc.
- The Red Pen Rule. Bút đỏ chỉ để chấm (tích, khoanh, số đến hạn, lề); không dùng cho nút, chữ thường hay lỗi.
- The Spoken Article Rule. Màu der/die/das chỉ làm nổi chính mạo từ; người mù màu vẫn đọc được mạo từ.
- The Season Sprig Rule. Màu mùa chỉ tô cành nhỏ cạnh ngày; không dùng cho nút, chữ hay trạng thái.
- The Thin Line Rule. Trang, thẻ chủ đề, ô ngày dùng viền 1 px, không bóng; không bóng đổ phẳng không nhoè.
Elevation:
- Thẻ: 0 1px 2px oklch(30% 0.06 285 / 0.10), 0 3px 8px oklch(30% 0.06 285 / 0.06)
- Nổi: 0 2px 4px oklch(30% 0.06 285 / 0.12), 0 10px 24px oklch(30% 0.06 285 / 0.14)
Don't:
- Don't dùng nền kem, giấy ngả vàng hay be làm nền.
- Don't dùng chữ tròn kiểu app thiếu nhi, nút viên thuốc, bo góc lớn.
- Don't vẽ minh hoạ cảnh (đồi, cây tròn, mặt trời) hay hình nền sọc lặp.
- Don't dùng bút đỏ cho nút hay lỗi; lỗi dùng màu gạch kèm chữ.
- Don't đặt nhãn nhỏ phía trên tiêu đề, lời chào chung chung ở đầu trang.
- Don't dùng emoji thay biểu tượng; dùng Lucide hoặc SVG riêng.
- Don't dùng ô số lớn kèm nhãn nhỏ kiểu bảng số liệu.
- Don't chen bảng nâng cấp vào giữa phiên học.
Do:
- Do giữ từ tiếng Đức là chữ lớn nhất trên màn hình học.
- Do nói số liệu bằng câu ("Hôm nay có 12 thẻ cần ôn và 9 từ mới") và kèm tên mức bên cạnh hình cây.
- Do đi kèm chữ và biểu tượng với mọi màu trạng thái.
- Do đặt `lang="de"` cho chữ tiếng Đức.
- (+2 more)
Full: .impeccable/design-full.md (read a section when you need its detail)
