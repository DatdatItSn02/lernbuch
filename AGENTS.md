# Ghi chú cho Claude

- Trả lời người dùng bằng tiếng Việt, ngắn gọn.
- Database: không khoá ngoại; thời gian là INT Unix timestamp (`$dateFormat = 'U'`), trừ bảng của Laravel; cột audit `created_by`, `updated_by`, `deleted_by`. Nguồn gốc cấu trúc là `database/sql/schema.sql`; migration ban đầu sinh bằng `tools/schema/gen_migrations.py`, thay đổi sau này viết migration mới.
- Viết xong chức năng nào thì tự viết test, có cả trường hợp lỗi, và chạy trước khi giao.
- Hỏi trước khi bỏ một chức năng hay một cột dữ liệu.
- Môi trường của Claude không tải được gói từ Packagist: kiểm PHP bằng CI (đẩy nhánh `work/**`, đọc nhánh `ci-results`).
