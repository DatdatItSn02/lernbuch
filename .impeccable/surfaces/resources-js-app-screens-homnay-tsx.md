---
version: 1
slug: "resources-js-app-screens-homnay-tsx"
primary_target: "resources/js/app/screens/HomNay.tsx"
related_targets: []
---

# Hôm nay

Phạm vi: trang đầu của app học (`/app/hom-nay`). Chế độ Operate. Người học mở app nhiều lần mỗi ngày trên điện thoại, mỗi lượt vài phút; việc chính: biết hôm nay cần làm gì rồi bấm Học ngay. Bản G0 chạy bằng dữ liệu mẫu, ghi rõ trên giao diện.

## Direction contract

THESIS: Ngày hôm nay được đọc qua 14 ngày quanh nó: dải ngày là xương sống của trang, hôm nay là ô sáng nhất, việc cần làm treo ngay dưới ô đó. Không dùng lưới ô số liệu kiểu dashboard.

OWN-WORLD: Nền giấy ấm, tờ sổ trắng ngà giữa màn hình, chữ mực nâu; Baloo 2 cho lời chào, Nunito cho mọi chữ làm việc; màu lá chỉ cho Học ngay và ô đang chọn; cảnh mùa (thu: cây cam) chỉ ở dải đầu trang; mỗi ô ngày vẽ hình cây theo lượng ôn của ngày đó.

STORY: Người học thấy chuỗi ngày và xu, chọn hôm nay (mặc định) để thấy số thẻ đến hạn, từ mới, mục tiêu ngày, rồi bấm Học ngay; chọn ngày cũ để xem đã làm gì, chọn ngày tới để biết sẽ đến hạn bao nhiêu; đi tiếp vào chủ đề đến hạn hoặc chọn số từ mới mỗi lượt.

FIRST VIEWPORT: Điện thoại 390×844: dải cảnh mùa thấp có lời chào và ngày; dải 14 ô ngày (10 ngày qua, hôm nay, 3 ngày tới) kèm "Chuỗi 6 ngày · 340 xu"; dưới là khối của ngày đang chọn: nút Học ngay cao 48 px, dòng phụ số thẻ và chủ đề; mục tiêu ngày. Chủ đề đến hạn bắt đầu ở mép dưới. Máy tính: dải ngày ngang cả tờ sổ, dưới chia hai cột.

FORM: Chuỗi 14 ngày làm trục, vị trí 7 trong danh sách của mình; người dùng chọn. seed:8fbeab27c3b64997

FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance
