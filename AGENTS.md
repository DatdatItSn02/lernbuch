# Ghi chú cho Claude

- Trả lời người dùng bằng tiếng Việt, ngắn gọn.
- Database: không khoá ngoại; thời gian là INT Unix timestamp (`$dateFormat = 'U'`), trừ bảng của Laravel; cột audit `created_by`, `updated_by`, `deleted_by`. Nguồn gốc cấu trúc là `database/sql/schema.sql`; migration ban đầu sinh bằng `tools/schema/gen_migrations.py`, thay đổi sau này viết migration mới.
- Viết xong chức năng nào thì tự viết test, có cả trường hợp lỗi, và chạy trước khi giao.
- Hỏi trước khi bỏ một chức năng hay một cột dữ liệu.
- Môi trường của Claude không tải được gói từ Packagist: kiểm PHP bằng CI (đẩy nhánh `work/**`, đọc nhánh `ci-results`).
- Máy Windows của người dùng chạy bằng `setup\*.bat` giống LoWo: công cụ dùng chung ở `F:\dev` (PHP 8.5, Node 26, MySQL 9.7 cổng 3307), web ở http://127.0.0.1:8001. Sửa script trong `setup\` thì chạy workflow `windows` (Windows PowerShell 5.1); file `.ps1` phải có BOM, `.bat` chỉ chữ không dấu. Kết quả trên máy người dùng nằm ở `setup\ket-qua-*.txt`.
