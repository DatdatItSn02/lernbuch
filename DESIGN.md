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

# Design System: Lernbuch Online

<!-- impeccable:design-map -->
Name: Lernbuch Online
Colors: primary: leaf oklch(50% 0.12 150); neutral: paper oklch(97.5% 0.012 85); semantic: danger oklch(52% 0.17 28); primary: leaf-strong oklch(44% 0.115 150); primary: leaf-tint oklch(94% 0.04 150) (+21 more in the sidecar)
Fonts: Baloo 2 (display, headline); Nunito (title, body, label, study-word); Patrick Hand (handwritten)
Spacing: 1 4px, 2 8px, 3 12px, 4 16px, 5 24px, 6 32px, 7 48px, 8 64px
Radius: sm 6px, md 10px, lg 16px, pill 999px
North Star: "Sổ tay khu vườn"
Rules:
- The One Leaf Rule. Màu lá chỉ dành cho hành động chính, trạng thái đúng và mục đang chọn.
- The Spoken Article Rule. Màu der/die/das chỉ làm nổi chính mạo từ; người mù màu vẫn đọc được mạo từ.
- The Season Frame Rule. Màu mùa không dùng cho nút, chữ hay trạng thái.
- The Working Type Rule. Baloo 2 không dùng cho nút, nhãn, số liệu.
- The Soft Shadow Rule. Không dùng bóng đổ phẳng không nhoè (kiểu `4px 4px 0`).
Elevation:
- Tờ sổ: 0 1px 2px oklch(27% 0.025 60 / 0.08), 0 4px 12px oklch(27% 0.025 60 / 0.06)
- Nổi: 0 2px 4px oklch(27% 0.025 60 / 0.10), 0 12px 28px oklch(27% 0.025 60 / 0.12)
Don't:
- Don't đặt cảnh mùa hay minh hoạ giữa phiên học.
- Don't dùng bóng đổ phẳng không nhoè, chữ gradient, kính mờ trang trí.
- Don't đặt nhãn nhỏ phía trên tiêu đề.
- Don't dùng emoji hay ký tự thay biểu tượng; biểu tượng vẽ bằng Lucide hoặc SVG riêng; OpenMoji chỉ là minh hoạ cho từ.
- Don't dùng ô số lớn kèm nhãn nhỏ kiểu bảng số liệu; số liệu nói bằng câu hoặc bằng cây.
- Don't chen bảng nâng cấp vào giữa phiên học.
Do:
- Do giữ từ tiếng Đức là chữ lớn nhất trên màn hình học.
- Do đi kèm chữ và biểu tượng với mọi màu trạng thái.
- Do đặt `lang="de"` và `hyphens: auto` cho chữ tiếng Đức.
- Do theo màu hệ thống cho chữ được chọn, con trỏ nhập, thanh cuộn, vòng focus.
- Do để tên đọc của nút bắt đầu bằng đúng chữ nhìn thấy trên nút.
- Do dùng 6 hình hạt, mầm, lá, nụ, hoa, quả chỉ để chỉ mức thuộc.
Full: .impeccable/design-full.md (read a section when you need its detail)
