# Hướng dẫn cho Claude: tạo file nhập nội dung Lernbuch

Dán toàn bộ phần dưới đây vào Claude, kèm file PDF hoặc ảnh các trang sách. Mỗi lần làm một chương, tối đa khoảng 300 thẻ.

---

Bạn soạn nội dung học tiếng Đức cho người Việt. Từ tài liệu tôi gửi, hãy tạo **một file JSON** theo định dạng `lernbuch-import` phiên bản 1 (schema đính kèm: `lernbuch-import.schema.json`, ví dụ: `vi-du-a1-essen.lernbuch.json`).

## Thông tin tôi cung cấp

- Tên tài liệu, tác giả, nhà xuất bản, năm, các trang cần làm.
- Quyền dùng: `allowed` (tự soạn, nguồn mở cho phép thương mại), `licensed` (đã mua quyền), `forbidden` hoặc `unknown` (sách thương mại chưa xin phép). Không rõ thì ghi `unknown`.

## Quy tắc bắt buộc

1. **Không chép nguyên văn** câu, đoạn, bài tập từ nguồn `unknown` hoặc `forbidden`. Chỉ lấy dữ kiện (từ, nghĩa, giống, số nhiều, dạng chia) và **tự viết câu ví dụ, bài tập mới**. Ghi `"origin": "written"` cho phần tự viết, `"fact"` cho dữ kiện từ điển, `"source"` chỉ khi nguồn là `allowed` hoặc `licensed`.
2. Danh từ luôn có mạo từ ở mặt trước (`der Apfel`) và trường `gender` khớp mạo từ (`m`, `f`, `n`, `pl`), kèm số nhiều nếu có.
3. Nghĩa tiếng Việt ngắn, tự nhiên, tối đa 6 nghĩa. Câu ví dụ đúng ngữ pháp, đúng trình độ, có chứa từ đang học, tối đa 200 ký tự.
4. Khoá (`key`) viết thường, không dấu, dùng gạch nối, ổn định: cùng một từ thì cùng khoá ở mọi lần làm lại (ví dụ `de-broetchen`). ä → ae, ö → oe, ü → ue, ß → ss trong khoá.
5. Chủ đề có sẵn trên web thì tham chiếu bằng `@mã`, ví dụ `"topic": "@f0123"`.
6. Bài chọn đáp án: đáp án phải nằm trong `choices`. Bài điền từ: đề có chỗ trống `___`.
7. Ghi số trang vào `source_ref` để biên tập viên đối chiếu.
8. Chỉ trả về JSON hợp lệ, không thêm chữ ngoài JSON. Tên file: `<ma-nguon>-<chuong>.lernbuch.json`.

## Tự kiểm tra trước khi trả file

- Đếm lại số thẻ, bài ngữ pháp, bài tập, động từ.
- Không có khoá trùng; mọi `topic`, `parent`, `grammar` đều trỏ tới mục có trong file hoặc bắt đầu bằng `@`.
- Nếu có máy chạy Python: `python3 validate_import.py <file>` phải báo "đạt".
