-- =====================================================================
-- Lernbuch Online — schema database v0.2 (2026-11-27: base_lang cho bài ngữ pháp, bộ bài tập)
-- Đích: MySQL 8.4 LTS. Cú pháp giữ ở mức chạy được cả MariaDB 10.6+ (máy dev).
--
-- Quy ước (theo LoWo):
--   * Không dùng khoá ngoại. Mọi cột *_id đều có chỉ mục; toàn vẹn do ứng dụng
--     đảm bảo + job dò dữ liệu mồ côi mỗi đêm.
--   * Thời gian lưu INT UNSIGNED = Unix timestamp (UTC). Giá trị chỉ có ngày lưu
--     mốc 00:00 theo múi giờ của người dùng. Laravel: $dateFormat = 'U'.
--   * Bảng có trang quản lý có đủ: created_at, created_by, updated_at, updated_by,
--     deleted_at, deleted_by.
--   * Tiền: BIGINT UNSIGNED, đơn vị đồng. Trạng thái: VARCHAR + enum trong PHP.
--   * Mã, slug, hash, mã ngôn ngữ: ascii_bin. Chữ cần so khớp chính xác
--     (dạng từ, động từ nguyên mẫu): utf8mb4_bin, để "zahlen" khác "zählen".
--   * Ngoại lệ: bảng của Laravel và gói đi kèm (mục 9) giữ nguyên dạng gốc.
-- =====================================================================

SET NAMES utf8mb4;
CREATE DATABASE IF NOT EXISTS lernbuch CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE lernbuch;

-- =====================================================================
-- 1. TÀI KHOẢN, QUYỀN, QUYỀN RIÊNG TƯ
-- =====================================================================

CREATE TABLE users (
  id                        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id                 CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'ULID dùng trong URL và API',
  email                     VARCHAR(191) NOT NULL,
  email_verified_at         INT UNSIGNED NULL,
  password                  VARCHAR(255) NULL COMMENT 'NULL nếu chỉ đăng nhập Google',
  name                      VARCHAR(100) NOT NULL,
  username                  VARCHAR(32) NULL COMMENT 'tên hiện trên bảng xếp hạng',
  avatar_path               VARCHAR(255) NULL,
  ui_locale                 VARCHAR(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'vi',
  timezone                  VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'Asia/Ho_Chi_Minh',
  birth_date                INT NULL COMMENT 'ngày sinh lúc 00:00 UTC; INT có dấu vì có thể trước 1970; AI chỉ mở từ 18 tuổi',
  status                    VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'active' COMMENT 'active, suspended, pending_deletion',
  is_staff                  TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = nhân viên, bắt buộc 2FA',
  two_factor_secret         TEXT NULL COMMENT 'mã hoá ở ứng dụng',
  two_factor_recovery_codes TEXT NULL COMMENT 'mã hoá ở ứng dụng',
  two_factor_confirmed_at   INT UNSIGNED NULL,
  remember_token            VARCHAR(100) NULL,
  referral_code             VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NULL,
  referred_by               BIGINT UNSIGNED NULL,
  last_login_at             INT UNSIGNED NULL,
  last_active_at            INT UNSIGNED NULL,
  deletion_requested_at     INT UNSIGNED NULL,
  created_at                INT UNSIGNED NULL,
  created_by                BIGINT UNSIGNED NULL,
  updated_at                INT UNSIGNED NULL,
  updated_by                BIGINT UNSIGNED NULL,
  deleted_at                INT UNSIGNED NULL,
  deleted_by                BIGINT UNSIGNED NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_public_id (public_id),
  UNIQUE KEY uq_users_email (email),
  UNIQUE KEY uq_users_username (username),
  UNIQUE KEY uq_users_referral_code (referral_code),
  KEY ix_users_referred_by (referred_by),
  KEY ix_users_status (status),
  KEY ix_users_created_at (created_at),
  KEY ix_users_created_by (created_by),
  KEY ix_users_updated_by (updated_by),
  KEY ix_users_deleted_by (deleted_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tài khoản: học viên và nhân viên';

CREATE TABLE user_settings (
  user_id            BIGINT UNSIGNED NOT NULL,
  current_course_id  INT UNSIGNED NULL,
  new_per_session    SMALLINT UNSIGNED NOT NULL DEFAULT 20 COMMENT '0 = tất cả',
  daily_goal         SMALLINT UNSIGNED NOT NULL DEFAULT 20 COMMENT 'số thẻ mỗi ngày',
  reminder_time      CHAR(5) CHARACTER SET ascii COLLATE ascii_bin NULL COMMENT 'HH:MM theo giờ người dùng',
  reminder_channels  JSON NULL COMMENT '["email","push"]',
  prefs              JSON NULL COMMENT 'mặt trước thẻ, tự phát âm, hiện ví dụ, AI tự giải thích, ngôn ngữ nghĩa, mùa, sáng/tối, cỡ chữ',
  updated_at         INT UNSIGNED NULL,
  PRIMARY KEY (user_id),
  KEY ix_user_settings_course (current_course_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cài đặt học và giao diện, đồng bộ giữa các máy';

CREATE TABLE social_accounts (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id           BIGINT UNSIGNED NOT NULL,
  provider          VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'google, facebook, apple',
  provider_user_id  VARCHAR(191) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  email             VARCHAR(191) NULL,
  created_at        INT UNSIGNED NULL,
  updated_at        INT UNSIGNED NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_social_provider_uid (provider, provider_user_id),
  KEY ix_social_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Đăng nhập qua Google, Facebook, Apple';

CREATE TABLE user_devices (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id       BIGINT UNSIGNED NOT NULL,
  device_uid    CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'ULID lưu trên máy',
  name          VARCHAR(120) NULL COMMENT 'vd Chrome trên Windows',
  platform      VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NULL COMMENT 'web, pwa, ios, android',
  last_ip       VARBINARY(16) NULL,
  last_seen_at  INT UNSIGNED NULL,
  revoked_at    INT UNSIGNED NULL,
  created_at    INT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user_devices (user_id, device_uid),
  KEY ix_user_devices_seen (user_id, last_seen_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Thiết bị đang đăng nhập: giới hạn số máy, đăng xuất từ xa';

CREATE TABLE login_events (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     BIGINT UNSIGNED NULL,
  email       VARCHAR(191) NULL COMMENT 'ghi khi đăng nhập sai',
  event       VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'login, logout, failed, two_factor_failed, password_reset, locked',
  ip          VARBINARY(16) NULL,
  user_agent  VARCHAR(255) NULL,
  created_at  INT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  KEY ix_login_events_user (user_id, created_at),
  KEY ix_login_events_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Lịch sử đăng nhập, giữ 90 ngày';

CREATE TABLE consents (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id           BIGINT UNSIGNED NOT NULL,
  kind              VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'terms, privacy, marketing_email, analytics_cookies, ai_processing',
  document_version  VARCHAR(20) NOT NULL,
  granted           TINYINT(1) NOT NULL,
  source            VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'web' COMMENT 'web, app, admin',
  ip                VARBINARY(16) NULL,
  created_at        INT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  KEY ix_consents_user_kind (user_id, kind, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bằng chứng đồng ý và rút đồng ý xử lý dữ liệu cá nhân';

CREATE TABLE data_requests (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id       BIGINT UNSIGNED NOT NULL,
  kind          VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'export, delete',
  status        VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'pending' COMMENT 'pending, processing, done, rejected, canceled',
  file_path     VARCHAR(255) NULL,
  note          VARCHAR(500) NULL,
  requested_at  INT UNSIGNED NOT NULL,
  due_at        INT UNSIGNED NULL,
  completed_at  INT UNSIGNED NULL,
  handled_by    BIGINT UNSIGNED NULL,
  PRIMARY KEY (id),
  KEY ix_data_requests_user (user_id),
  KEY ix_data_requests_status (status, due_at),
  KEY ix_data_requests_handler (handled_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Yêu cầu tải dữ liệu hoặc xoá tài khoản';

-- Vai trò và quyền (spatie/laravel-permission, model tuỳ biến để dùng int timestamp)
CREATE TABLE permissions (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name        VARCHAR(125) NOT NULL,
  guard_name  VARCHAR(125) NOT NULL,
  label       VARCHAR(160) NULL COMMENT 'tên tiếng Việt hiển thị',
  created_at  INT UNSIGNED NULL,
  created_by  BIGINT UNSIGNED NULL,
  updated_at  INT UNSIGNED NULL,
  updated_by  BIGINT UNSIGNED NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_permissions_name_guard (name, guard_name),
  KEY ix_permissions_created_by (created_by),
  KEY ix_permissions_updated_by (updated_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Quyền';

CREATE TABLE roles (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name         VARCHAR(125) NOT NULL,
  guard_name   VARCHAR(125) NOT NULL,
  label        VARCHAR(160) NULL,
  description  VARCHAR(255) NULL,
  created_at   INT UNSIGNED NULL,
  created_by   BIGINT UNSIGNED NULL,
  updated_at   INT UNSIGNED NULL,
  updated_by   BIGINT UNSIGNED NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_roles_name_guard (name, guard_name),
  KEY ix_roles_created_by (created_by),
  KEY ix_roles_updated_by (updated_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Vai trò';

CREATE TABLE model_has_permissions (
  permission_id  BIGINT UNSIGNED NOT NULL,
  model_type     VARCHAR(125) NOT NULL,
  model_id       BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (permission_id, model_id, model_type),
  KEY ix_mhp_model (model_id, model_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Quyền gán thẳng cho người dùng';

CREATE TABLE model_has_roles (
  role_id     BIGINT UNSIGNED NOT NULL,
  model_type  VARCHAR(125) NOT NULL,
  model_id    BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (role_id, model_id, model_type),
  KEY ix_mhr_model (model_id, model_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Vai trò của người dùng';

CREATE TABLE role_has_permissions (
  permission_id  BIGINT UNSIGNED NOT NULL,
  role_id        BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (permission_id, role_id),
  KEY ix_rhp_role (role_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Quyền của vai trò';

CREATE TABLE audit_logs (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  actor_id      BIGINT UNSIGNED NULL,
  action        VARCHAR(60) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'vd user.suspend, order.refund, card.update, impersonate.start',
  subject_type  VARCHAR(60) CHARACTER SET ascii COLLATE ascii_bin NULL,
  subject_id    BIGINT UNSIGNED NULL,
  changes       JSON NULL COMMENT '{"before":{},"after":{}}',
  ip            VARBINARY(16) NULL,
  user_agent    VARCHAR(255) NULL,
  created_at    INT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  KEY ix_audit_actor (actor_id, created_at),
  KEY ix_audit_subject (subject_type, subject_id),
  KEY ix_audit_action (action, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Nhật ký thao tác của nhân viên, chỉ thêm không sửa';

-- =====================================================================
-- 2. NỘI DUNG: NGÔN NGỮ, KHÓA HỌC, CHỦ ĐỀ, THẺ, NGỮ PHÁP, BÀI TẬP
-- =====================================================================

CREATE TABLE languages (
  code           VARCHAR(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'de, en, zh, vi, ja…',
  name_vi        VARCHAR(60) NOT NULL,
  native_name    VARCHAR(60) NOT NULL,
  tts_voice      VARCHAR(60) CHARACTER SET ascii COLLATE ascii_bin NULL COMMENT 'vd de-DE-KatjaNeural',
  special_chars  VARCHAR(60) NULL COMMENT 'nút gõ nhanh, vd äöüßÄÖÜ',
  is_target      TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = có khóa học',
  is_meaning     TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = dùng làm nghĩa',
  is_ui          TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = ngôn ngữ giao diện',
  sort           SMALLINT NOT NULL DEFAULT 0,
  created_at     INT UNSIGNED NULL,
  created_by     BIGINT UNSIGNED NULL,
  updated_at     INT UNSIGNED NULL,
  updated_by     BIGINT UNSIGNED NULL,
  deleted_at     INT UNSIGNED NULL,
  deleted_by     BIGINT UNSIGNED NULL,
  PRIMARY KEY (code),
  KEY ix_languages_created_by (created_by),
  KEY ix_languages_updated_by (updated_by),
  KEY ix_languages_deleted_by (deleted_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Ngôn ngữ';

CREATE TABLE courses (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug           VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  target_lang    VARCHAR(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  base_lang      VARCHAR(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'ngôn ngữ giải thích',
  meaning_langs  JSON NOT NULL COMMENT '["vi","en","zh"]',
  title          VARCHAR(120) NOT NULL,
  description    TEXT NULL,
  cover_path     VARCHAR(255) NULL,
  level_min      TINYINT UNSIGNED NOT NULL DEFAULT 1,
  level_max      TINYINT UNSIGNED NOT NULL DEFAULT 4,
  status         VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'draft' COMMENT 'draft, published, archived',
  sort           SMALLINT NOT NULL DEFAULT 0,
  created_at     INT UNSIGNED NULL,
  created_by     BIGINT UNSIGNED NULL,
  updated_at     INT UNSIGNED NULL,
  updated_by     BIGINT UNSIGNED NULL,
  deleted_at     INT UNSIGNED NULL,
  deleted_by     BIGINT UNSIGNED NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_courses_slug (slug),
  UNIQUE KEY uq_courses_pair (target_lang, base_lang),
  KEY ix_courses_base_lang (base_lang),
  KEY ix_courses_created_by (created_by),
  KEY ix_courses_updated_by (updated_by),
  KEY ix_courses_deleted_by (deleted_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Khóa học = ngôn ngữ đích + ngôn ngữ giải thích';

CREATE TABLE card_collections (
  code              VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'vocab, phrase, comm, pattern, verbprep, sepverb, name',
  name_vi           VARCHAR(60) NOT NULL,
  name_en           VARCHAR(60) NULL,
  single_word_only  TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = chỉ nhận từ đơn',
  sort              SMALLINT NOT NULL DEFAULT 0,
  PRIMARY KEY (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Loại thẻ';

CREATE TABLE topics (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  lang         VARCHAR(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  parent_id    INT UNSIGNED NULL,
  depth        TINYINT UNSIGNED NOT NULL COMMENT '1 nhóm lớn, 2 chủ đề vừa, 3 chủ đề nhỏ',
  collection   VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NULL COMMENT 'chủ đề nhỏ thuộc đúng 1 loại thẻ',
  code         VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'giữ mã cũ, vd f0001',
  slug         VARCHAR(160) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  name_vi      VARCHAR(160) NOT NULL,
  name_en      VARCHAR(160) NULL,
  icon         VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NULL COMMENT 'mã OpenMoji hoặc p-<tên>',
  sort         INT NOT NULL DEFAULT 0,
  card_count   INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'đếm sẵn thẻ đã xuất bản',
  access_tier  TINYINT UNSIGNED NOT NULL DEFAULT 2 COMMENT '0 khách, 1 Free, 2 Premium',
  status       VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'published',
  created_at   INT UNSIGNED NULL,
  created_by   BIGINT UNSIGNED NULL,
  updated_at   INT UNSIGNED NULL,
  updated_by   BIGINT UNSIGNED NULL,
  deleted_at   INT UNSIGNED NULL,
  deleted_by   BIGINT UNSIGNED NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_topics_lang_code (lang, code),
  UNIQUE KEY uq_topics_lang_slug (lang, slug),
  KEY ix_topics_parent (parent_id, sort),
  KEY ix_topics_browse (lang, collection, depth, status, sort),
  KEY ix_topics_created_by (created_by),
  KEY ix_topics_updated_by (updated_by),
  KEY ix_topics_deleted_by (deleted_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Chủ đề 3 tầng';

CREATE TABLE content_sources (
  id              SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code            VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  name            VARCHAR(160) NOT NULL,
  license         VARCHAR(60) NULL COMMENT 'vd CC BY 2.0 FR, CC BY-SA 4.0, tự soạn, chưa rõ',
  commercial_use  VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'unknown' COMMENT 'allowed, licensed, forbidden, unknown; ghi để truy lại; nguồn chưa có quyền chỉ lấy dữ kiện, không chép nguyên văn',
  attribution     TEXT NULL,
  url             VARCHAR(255) NULL,
  notes           TEXT NULL,
  created_at      INT UNSIGNED NULL,
  created_by      BIGINT UNSIGNED NULL,
  updated_at      INT UNSIGNED NULL,
  updated_by      BIGINT UNSIGNED NULL,
  deleted_at      INT UNSIGNED NULL,
  deleted_by      BIGINT UNSIGNED NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sources_code (code),
  KEY ix_sources_commercial (commercial_use),
  KEY ix_sources_created_by (created_by),
  KEY ix_sources_updated_by (updated_by),
  KEY ix_sources_deleted_by (deleted_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Nguồn nội dung và giấy phép';

CREATE TABLE media_assets (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  kind          VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'audio, image',
  hash          CHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'audio: sha1(giọng|tốc độ|câu); hình: sha1 nội dung',
  disk          VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'r2',
  path          VARCHAR(255) NOT NULL,
  mime          VARCHAR(60) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  bytes         INT UNSIGNED NULL,
  duration_ms   INT UNSIGNED NULL,
  voice         VARCHAR(60) CHARACTER SET ascii COLLATE ascii_bin NULL,
  speed         VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NULL COMMENT 'normal, slow',
  text_content  VARCHAR(600) NULL COMMENT 'câu đã đọc',
  source_id     SMALLINT UNSIGNED NULL,
  created_at    INT UNSIGNED NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_media_kind_hash (kind, hash),
  KEY ix_media_source (source_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Sổ đăng ký file audio và hình trên kho file';

CREATE TABLE cards (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  lang           VARCHAR(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  collection     VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  card_key       VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'khoá ổn định, giữ từ Lernbuch',
  owner_user_id  BIGINT UNSIGNED NULL COMMENT 'NULL = nội dung hệ thống; có giá trị = thẻ riêng của người dùng',
  topic_id       INT UNSIGNED NULL COMMENT 'đúng 1 chủ đề nhỏ',
  front          VARCHAR(500) NOT NULL,
  article        VARCHAR(10) NULL COMMENT 'der, die, das',
  gender         CHAR(1) CHARACTER SET ascii COLLATE ascii_bin NULL COMMENT 'm, f, n, p (tô màu)',
  pos            VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NULL COMMENT 'loại từ',
  forms          VARCHAR(255) NULL COMMENT 'số nhiều hoặc dạng chia',
  level          TINYINT UNSIGNED NULL COMMENT '1–6 = A1–C2; NULL = chưa xếp',
  level_min      TINYINT UNSIGNED NULL,
  level_max      TINYINT UNSIGNED NULL,
  lektion        VARCHAR(20) NULL COMMENT 'bài trong giáo trình',
  image          VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NULL COMMENT 'mã OpenMoji hoặc p-<tên>',
  image_quality  TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 chưa có, 1 nhóm nghĩa, 2 khớp nghĩa, 3 tự vẽ',
  note           TEXT NULL,
  tip            TEXT NULL,
  cloze          VARCHAR(500) NULL,
  cloze_answer   VARCHAR(120) NULL,
  source_topic   VARCHAR(160) NULL COMMENT 'chủ đề gốc từ nguồn',
  extra          JSON NULL COMMENT 'dữ liệu riêng ngôn ngữ: giới từ đi kèm, Kasus, tách được…',
  search_text    VARCHAR(1500) NULL COMMENT 'không dấu: từ + nghĩa mọi thứ tiếng + dạng',
  status         VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'published' COMMENT 'draft, in_review, published, archived',
  source_id      SMALLINT UNSIGNED NULL,
  version        INT UNSIGNED NOT NULL DEFAULT 1,
  published_at   INT UNSIGNED NULL,
  created_at     INT UNSIGNED NULL,
  created_by     BIGINT UNSIGNED NULL,
  updated_at     INT UNSIGNED NULL,
  updated_by     BIGINT UNSIGNED NULL,
  deleted_at     INT UNSIGNED NULL,
  deleted_by     BIGINT UNSIGNED NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cards_key (lang, collection, card_key),
  KEY ix_cards_topic (topic_id, status),
  KEY ix_cards_level (lang, collection, level, status),
  KEY ix_cards_pos (lang, pos),
  KEY ix_cards_owner (owner_user_id),
  KEY ix_cards_source (source_id),
  KEY ix_cards_created_by (created_by),
  KEY ix_cards_updated_by (updated_by),
  KEY ix_cards_deleted_by (deleted_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Thẻ học: từ, cụm, câu giao tiếp, mẫu câu, động từ, tên riêng';

CREATE TABLE card_translations (
  card_id      BIGINT UNSIGNED NOT NULL,
  lang         VARCHAR(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'vi, en, zh…',
  meaning      VARCHAR(500) NOT NULL,
  note         VARCHAR(500) NULL,
  is_verified  TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = người đã duyệt',
  source_id    SMALLINT UNSIGNED NULL,
  updated_at   INT UNSIGNED NULL,
  updated_by   BIGINT UNSIGNED NULL,
  PRIMARY KEY (card_id, lang),
  KEY ix_card_tr_lang (lang, is_verified),
  KEY ix_card_tr_source (source_id),
  KEY ix_card_tr_updated_by (updated_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Nghĩa của thẻ theo từng thứ tiếng';

CREATE TABLE card_examples (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  card_id       BIGINT UNSIGNED NOT NULL,
  sort          TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 = câu chính',
  sentence      VARCHAR(600) NOT NULL,
  translations  JSON NULL COMMENT '{"vi":"…","en":"…","zh":"…"}',
  is_manual     TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = câu viết tay cho từ khó',
  source_id     SMALLINT UNSIGNED NULL,
  created_at    INT UNSIGNED NULL,
  updated_at    INT UNSIGNED NULL,
  updated_by    BIGINT UNSIGNED NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_card_examples_sort (card_id, sort),
  KEY ix_card_examples_source (source_id),
  KEY ix_card_examples_updated_by (updated_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Câu ví dụ, tối đa 4 câu mỗi thẻ';

CREATE TABLE card_relations (
  card_id     BIGINT UNSIGNED NOT NULL,
  related_id  BIGINT UNSIGNED NOT NULL,
  kind        VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'similar, synonym, antonym, family, confusable',
  score       DECIMAL(4,3) NULL,
  PRIMARY KEY (card_id, kind, related_id),
  KEY ix_card_rel_related (related_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Từ tương tự, đồng nghĩa, trái nghĩa, cùng họ, dễ nhầm';

CREATE TABLE word_forms (
  id       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  lang     VARCHAR(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  form     VARCHAR(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'viết thường, so khớp chính xác',
  card_id  BIGINT UNSIGNED NOT NULL,
  kind     VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'lemma, plural, conj, comparative…',
  PRIMARY KEY (id),
  UNIQUE KEY uq_word_forms (card_id, kind, form),
  KEY ix_word_forms_lookup (lang, form)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Dạng từ để tra chữ bấm trong câu (số nhiều, dạng chia)';

CREATE TABLE decks (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  owner_type   VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'user, classroom, system',
  owner_id     BIGINT UNSIGNED NULL,
  lang         VARCHAR(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  name         VARCHAR(160) NOT NULL,
  description  VARCHAR(500) NULL,
  visibility   VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'private' COMMENT 'private, unlisted, public',
  card_count   INT UNSIGNED NOT NULL DEFAULT 0,
  created_at   INT UNSIGNED NULL,
  created_by   BIGINT UNSIGNED NULL,
  updated_at   INT UNSIGNED NULL,
  updated_by   BIGINT UNSIGNED NULL,
  deleted_at   INT UNSIGNED NULL,
  deleted_by   BIGINT UNSIGNED NULL,
  PRIMARY KEY (id),
  KEY ix_decks_owner (owner_type, owner_id),
  KEY ix_decks_created_by (created_by),
  KEY ix_decks_updated_by (updated_by),
  KEY ix_decks_deleted_by (deleted_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bộ thẻ tự tạo của người dùng hoặc lớp học';

CREATE TABLE deck_cards (
  deck_id   BIGINT UNSIGNED NOT NULL,
  card_id   BIGINT UNSIGNED NOT NULL,
  sort      INT NOT NULL DEFAULT 0,
  added_at  INT UNSIGNED NOT NULL,
  PRIMARY KEY (deck_id, card_id),
  KEY ix_deck_cards_card (card_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Thẻ trong bộ thẻ tự tạo';

CREATE TABLE user_card_notes (
  user_id     BIGINT UNSIGNED NOT NULL,
  card_id     BIGINT UNSIGNED NOT NULL,
  is_starred  TINYINT(1) NOT NULL DEFAULT 0,
  note        TEXT NULL,
  created_at  INT UNSIGNED NULL,
  updated_at  INT UNSIGNED NULL,
  PRIMARY KEY (user_id, card_id),
  KEY ix_ucn_card (card_id),
  KEY ix_ucn_starred (user_id, is_starred)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Thẻ đánh dấu sao và ghi chú riêng';

CREATE TABLE search_history (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     BIGINT UNSIGNED NOT NULL,
  term        VARCHAR(120) NOT NULL,
  lang        VARCHAR(8) CHARACTER SET ascii COLLATE ascii_bin NULL,
  card_id     BIGINT UNSIGNED NULL COMMENT 'thẻ đã mở từ kết quả',
  created_at  INT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  KEY ix_search_user (user_id, created_at),
  KEY ix_search_card (card_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Lịch sử tra từ, giữ 100 mục gần nhất';

CREATE TABLE grammar_chapters (
  id          SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  lang        VARCHAR(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  code        VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  name_vi     VARCHAR(120) NOT NULL,
  name_en     VARCHAR(120) NULL,
  sort        SMALLINT NOT NULL DEFAULT 0,
  created_at  INT UNSIGNED NULL,
  created_by  BIGINT UNSIGNED NULL,
  updated_at  INT UNSIGNED NULL,
  updated_by  BIGINT UNSIGNED NULL,
  deleted_at  INT UNSIGNED NULL,
  deleted_by  BIGINT UNSIGNED NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_grammar_chapters (lang, code),
  KEY ix_gchap_created_by (created_by),
  KEY ix_gchap_updated_by (updated_by),
  KEY ix_gchap_deleted_by (deleted_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Chương ngữ pháp';

CREATE TABLE grammar_lessons (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  lang          VARCHAR(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  base_lang     VARCHAR(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'vi' COMMENT 'ngôn ngữ giải thích',
  chapter_id    SMALLINT UNSIGNED NULL,
  slug          VARCHAR(160) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  level         TINYINT UNSIGNED NULL COMMENT '1–4 = A1–B2; NULL = chuyên đề',
  title         VARCHAR(200) NOT NULL,
  summary       VARCHAR(500) NULL,
  blocks        JSON NOT NULL COMMENT 'khối: tiêu đề, đoạn, danh sách, bảng, công thức, mẹo, ghi chú, tóm tắt, ví dụ',
  access_tier   TINYINT UNSIGNED NOT NULL DEFAULT 2 COMMENT '0 khách, 1 Free, 2 Premium',
  is_public     TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = hiện công khai cho SEO',
  status        VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'published',
  source_id     SMALLINT UNSIGNED NULL,
  sort          INT NOT NULL DEFAULT 0,
  version       INT UNSIGNED NOT NULL DEFAULT 1,
  published_at  INT UNSIGNED NULL,
  created_at    INT UNSIGNED NULL,
  created_by    BIGINT UNSIGNED NULL,
  updated_at    INT UNSIGNED NULL,
  updated_by    BIGINT UNSIGNED NULL,
  deleted_at    INT UNSIGNED NULL,
  deleted_by    BIGINT UNSIGNED NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_grammar_lessons_slug (lang, base_lang, slug),
  KEY ix_glessons_browse (lang, base_lang, level, chapter_id, sort),
  KEY ix_glessons_chapter (chapter_id),
  KEY ix_glessons_source (source_id),
  KEY ix_glessons_created_by (created_by),
  KEY ix_glessons_updated_by (updated_by),
  KEY ix_glessons_deleted_by (deleted_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bài ngữ pháp';

CREATE TABLE exercise_sets (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  lang               VARCHAR(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  base_lang          VARCHAR(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'vi' COMMENT 'ngôn ngữ giải thích',
  slug               VARCHAR(160) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  title              VARCHAR(200) NOT NULL,
  section            VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'level (theo cấp), review (Ôn tập), plural (Số nhiều)',
  level              TINYINT UNSIGNED NULL,
  grammar_lesson_id  INT UNSIGNED NULL,
  topic_id           INT UNSIGNED NULL,
  description        VARCHAR(500) NULL,
  item_count         SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  access_tier        TINYINT UNSIGNED NOT NULL DEFAULT 2,
  status             VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'published',
  source_id          SMALLINT UNSIGNED NULL,
  sort               INT NOT NULL DEFAULT 0,
  created_at         INT UNSIGNED NULL,
  created_by         BIGINT UNSIGNED NULL,
  updated_at         INT UNSIGNED NULL,
  updated_by         BIGINT UNSIGNED NULL,
  deleted_at         INT UNSIGNED NULL,
  deleted_by         BIGINT UNSIGNED NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_exercise_sets_slug (lang, base_lang, slug),
  KEY ix_exsets_browse (lang, base_lang, section, level, sort),
  KEY ix_exsets_lesson (grammar_lesson_id),
  KEY ix_exsets_topic (topic_id),
  KEY ix_exsets_source (source_id),
  KEY ix_exsets_created_by (created_by),
  KEY ix_exsets_updated_by (updated_by),
  KEY ix_exsets_deleted_by (deleted_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bộ bài tập';

CREATE TABLE exercises (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  set_id        INT UNSIGNED NOT NULL,
  sig           VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'khoá ổn định, giữ từ Lernbuch',
  kind          VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'fill, mc, order, fix',
  prompt        TEXT NOT NULL,
  answers       JSON NOT NULL COMMENT 'các đáp án được chấp nhận',
  choices       JSON NULL COMMENT 'lựa chọn (mc) hoặc mảnh câu (order)',
  hint          VARCHAR(500) NULL,
  explanation   TEXT NULL,
  translations  JSON NULL,
  sort          INT NOT NULL DEFAULT 0,
  status        VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'published',
  source_id     SMALLINT UNSIGNED NULL,
  created_at    INT UNSIGNED NULL,
  created_by    BIGINT UNSIGNED NULL,
  updated_at    INT UNSIGNED NULL,
  updated_by    BIGINT UNSIGNED NULL,
  deleted_at    INT UNSIGNED NULL,
  deleted_by    BIGINT UNSIGNED NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_exercises_sig (sig),
  KEY ix_exercises_set (set_id, sort),
  KEY ix_exercises_source (source_id),
  KEY ix_exercises_created_by (created_by),
  KEY ix_exercises_updated_by (updated_by),
  KEY ix_exercises_deleted_by (deleted_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Câu bài tập: điền từ, trắc nghiệm, sắp xếp, sửa lỗi';

CREATE TABLE verbs (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  lang          VARCHAR(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  card_id       BIGINT UNSIGNED NULL COMMENT 'thẻ Từ vựng tương ứng',
  infinitive    VARCHAR(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  level         TINYINT UNSIGNED NULL,
  is_separable  TINYINT(1) NOT NULL DEFAULT 0,
  prefix        VARCHAR(20) NULL,
  auxiliary     VARCHAR(10) NULL COMMENT 'haben, sein',
  is_irregular  TINYINT(1) NOT NULL DEFAULT 0,
  form_du       VARCHAR(80) NULL,
  form_er       VARCHAR(80) NULL,
  preterite     VARCHAR(80) NULL,
  perfect       VARCHAR(120) NULL,
  meanings      JSON NULL,
  conjugation   JSON NULL COMMENT 'bảng Präsens, Präteritum, Perfekt, Imperativ…',
  access_tier   TINYINT UNSIGNED NOT NULL DEFAULT 1,
  status        VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'published',
  source_id     SMALLINT UNSIGNED NULL,
  created_at    INT UNSIGNED NULL,
  created_by    BIGINT UNSIGNED NULL,
  updated_at    INT UNSIGNED NULL,
  updated_by    BIGINT UNSIGNED NULL,
  deleted_at    INT UNSIGNED NULL,
  deleted_by    BIGINT UNSIGNED NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_verbs (lang, infinitive),
  KEY ix_verbs_card (card_id),
  KEY ix_verbs_level (lang, level),
  KEY ix_verbs_source (source_id),
  KEY ix_verbs_created_by (created_by),
  KEY ix_verbs_updated_by (updated_by),
  KEY ix_verbs_deleted_by (deleted_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Động từ và bảng chia';

-- =====================================================================
-- 3. HỌC VÀ TIẾN ĐỘ
-- =====================================================================

CREATE TABLE user_courses (
  user_id           BIGINT UNSIGNED NOT NULL,
  course_id         INT UNSIGNED NOT NULL,
  goal              VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NULL COMMENT 'study_abroad, vocational, work, exam, travel, other',
  level_goal        TINYINT UNSIGNED NULL,
  placement_level   TINYINT UNSIGNED NULL,
  current_topic_id  INT UNSIGNED NULL,
  enrolled_at       INT UNSIGNED NOT NULL,
  last_studied_at   INT UNSIGNED NULL,
  PRIMARY KEY (user_id, course_id),
  KEY ix_user_courses_course (course_id),
  KEY ix_user_courses_topic (current_topic_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Khóa học người dùng đang học';

CREATE TABLE card_progress (
  user_id        BIGINT UNSIGNED NOT NULL,
  card_id        BIGINT UNSIGNED NOT NULL,
  box            TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 Mới, 1 Đang học, 2 Quen, 3 Khá, 4 Thuộc, 5 Thành thạo',
  due_at         INT UNSIGNED NULL,
  reps           SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  lapses         SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  last_rating    TINYINT UNSIGNED NULL COMMENT '0 Quên, 1 Khó, 2 Tốt, 3 Dễ',
  stability      FLOAT NULL COMMENT 'dự phòng cho FSRS',
  difficulty     FLOAT NULL COMMENT 'dự phòng cho FSRS',
  first_seen_at  INT UNSIGNED NULL,
  last_seen_at   INT UNSIGNED NULL,
  PRIMARY KEY (user_id, card_id),
  KEY ix_progress_due (user_id, due_at),
  KEY ix_progress_box (user_id, box),
  KEY ix_progress_card (card_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tiến độ từng thẻ của từng người (SRS 6 mức)';

-- Bảng lớn nhất. Chia partition theo năm; thêm năm mới trước 1/1 bằng
-- ALTER TABLE review_logs REORGANIZE PARTITION pmax INTO (...).
CREATE TABLE review_logs (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id      BIGINT UNSIGNED NOT NULL,
  card_id      BIGINT UNSIGNED NOT NULL,
  session_id   BIGINT UNSIGNED NULL,
  client_uid   CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NULL COMMENT 'ULID do máy tạo, chống gửi trùng',
  mode         VARCHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'flashcard, quiz, listening, typing, match, mixed, sentence',
  rating       TINYINT UNSIGNED NOT NULL COMMENT '0 Quên, 1 Khó, 2 Tốt, 3 Dễ',
  box_before   TINYINT UNSIGNED NOT NULL,
  box_after    TINYINT UNSIGNED NOT NULL,
  coins        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  duration_ms  INT UNSIGNED NULL,
  reviewed_at  INT UNSIGNED NOT NULL,
  PRIMARY KEY (id, reviewed_at),
  UNIQUE KEY uq_review_client (user_id, client_uid, reviewed_at),
  KEY ix_review_user (user_id, reviewed_at),
  KEY ix_review_card (card_id, reviewed_at),
  KEY ix_review_session (session_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Nhật ký từng lượt chấm thẻ'
PARTITION BY RANGE (reviewed_at) (
  PARTITION p2026 VALUES LESS THAN (1798761600),
  PARTITION p2027 VALUES LESS THAN (1830297600),
  PARTITION p2028 VALUES LESS THAN (1861920000),
  PARTITION pmax  VALUES LESS THAN MAXVALUE
);

CREATE TABLE study_sessions (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id         BIGINT UNSIGNED NOT NULL,
  course_id       INT UNSIGNED NULL,
  topic_id        INT UNSIGNED NULL,
  mode            VARCHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  planned_new     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  planned_review  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  done_new        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  done_review     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  leveled_up      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  ratings         JSON NULL COMMENT 'số lượt theo mức chấm, vd {"0":2,"1":3,"2":10,"3":1}',
  coins           INT UNSIGNED NOT NULL DEFAULT 0,
  client          VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NULL COMMENT 'web, pwa, ios, android',
  started_at      INT UNSIGNED NOT NULL,
  ended_at        INT UNSIGNED NULL,
  PRIMARY KEY (id),
  KEY ix_study_sessions_user (user_id, started_at),
  KEY ix_study_sessions_course (course_id),
  KEY ix_study_sessions_topic (topic_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Phiên học, dùng cho bảng tổng kết cuối phiên';

CREATE TABLE user_daily_stats (
  user_id        BIGINT UNSIGNED NOT NULL,
  day_start      INT UNSIGNED NOT NULL COMMENT 'mốc 00:00 theo múi giờ người dùng (Unix)',
  reviews        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  new_cards      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  correct        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  exercises      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  ai_calls       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  coins          INT UNSIGNED NOT NULL DEFAULT 0,
  study_seconds  INT UNSIGNED NOT NULL DEFAULT 0,
  goal_met       TINYINT(1) NOT NULL DEFAULT 0,
  streak_frozen  TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (user_id, day_start),
  KEY ix_daily_day (day_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Số liệu mỗi ngày: chuỗi ngày, biểu đồ 14 ngày, xếp hạng tuần';

CREATE TABLE user_stats (
  user_id            BIGINT UNSIGNED NOT NULL,
  coins_total        BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'tổng xu đã nhận',
  coins_balance      BIGINT NOT NULL DEFAULT 0 COMMENT 'xu còn tiêu được',
  streak_current     INT UNSIGNED NOT NULL DEFAULT 0,
  streak_longest     INT UNSIGNED NOT NULL DEFAULT 0,
  streak_last_day    INT UNSIGNED NULL,
  freezes_available  TINYINT UNSIGNED NOT NULL DEFAULT 0,
  cards_seen         INT UNSIGNED NOT NULL DEFAULT 0,
  cards_mastered     INT UNSIGNED NOT NULL DEFAULT 0,
  reviews_total      INT UNSIGNED NOT NULL DEFAULT 0,
  updated_at         INT UNSIGNED NULL,
  PRIMARY KEY (user_id),
  KEY ix_user_stats_coins (coins_total)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Số liệu tổng của từng người';

CREATE TABLE coin_transactions (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id        BIGINT UNSIGNED NOT NULL,
  amount         INT NOT NULL COMMENT 'dương = nhận, âm = tiêu',
  reason         VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'study, exercise, achievement, referral, streak_freeze, admin_adjust',
  ref_type       VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NULL,
  ref_id         BIGINT UNSIGNED NULL,
  balance_after  BIGINT NOT NULL,
  created_by     BIGINT UNSIGNED NULL,
  created_at     INT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  KEY ix_coin_tx_user (user_id, created_at),
  KEY ix_coin_tx_ref (ref_type, ref_id),
  KEY ix_coin_tx_created_by (created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Sổ xu: cộng theo phiên, tiêu, điều chỉnh';

CREATE TABLE user_lesson_progress (
  user_id       BIGINT UNSIGNED NOT NULL,
  lesson_id     INT UNSIGNED NOT NULL,
  status        VARCHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'read' COMMENT 'read, completed',
  read_at       INT UNSIGNED NULL,
  completed_at  INT UNSIGNED NULL,
  PRIMARY KEY (user_id, lesson_id),
  KEY ix_ulp_lesson (lesson_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bài ngữ pháp đã đọc';

CREATE TABLE exercise_runs (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id      BIGINT UNSIGNED NOT NULL,
  set_id       INT UNSIGNED NULL,
  mode         VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'set, quick, cloze, conjugate, write, plural, wrong_review',
  grading      VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'instant' COMMENT 'instant = chấm từng câu, sheet = chấm cả phiếu',
  total        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  correct      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  coins        INT UNSIGNED NOT NULL DEFAULT 0,
  started_at   INT UNSIGNED NOT NULL,
  finished_at  INT UNSIGNED NULL,
  PRIMARY KEY (id),
  KEY ix_exruns_user (user_id, started_at),
  KEY ix_exruns_set (set_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Lượt làm bài tập';

CREATE TABLE user_exercise_stats (
  user_id        BIGINT UNSIGNED NOT NULL,
  exercise_id    BIGINT UNSIGNED NOT NULL,
  correct_count  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  wrong_count    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  last_correct   TINYINT(1) NULL,
  last_answer    VARCHAR(500) NULL,
  last_at        INT UNSIGNED NULL,
  PRIMARY KEY (user_id, exercise_id),
  KEY ix_ues_wrong (user_id, last_correct, last_at),
  KEY ix_ues_exercise (exercise_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Đúng/sai từng câu, dùng cho Ôn câu sai';

CREATE TABLE writing_submissions (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id        BIGINT UNSIGNED NOT NULL,
  card_id        BIGINT UNSIGNED NULL,
  kind           VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'sentence' COMMENT 'sentence, free_ask, exam_writing',
  content        TEXT NOT NULL,
  verdict        VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NULL COMMENT 'dung, gan_dung, sai',
  result         JSON NULL,
  ai_request_id  BIGINT UNSIGNED NULL,
  created_at     INT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  KEY ix_writing_user (user_id, created_at),
  KEY ix_writing_card (card_id),
  KEY ix_writing_ai (ai_request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Câu người học tự viết và kết quả AI chấm';

-- =====================================================================
-- 4. AI
-- =====================================================================

CREATE TABLE ai_prompts (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  kind           VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'translate, lookup, lesson, chat, grammar, sentence, answer, word, doc, ask',
  version        SMALLINT UNSIGNED NOT NULL,
  model          VARCHAR(60) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'vd gemini-3.1-flash-lite',
  system_prompt  TEXT NOT NULL,
  user_template  TEXT NOT NULL,
  max_tokens     SMALLINT UNSIGNED NOT NULL DEFAULT 800,
  temperature    DECIMAL(3,2) NOT NULL DEFAULT 0.20,
  is_active      TINYINT(1) NOT NULL DEFAULT 0,
  created_at     INT UNSIGNED NULL,
  created_by     BIGINT UNSIGNED NULL,
  updated_at     INT UNSIGNED NULL,
  updated_by     BIGINT UNSIGNED NULL,
  deleted_at     INT UNSIGNED NULL,
  deleted_by     BIGINT UNSIGNED NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ai_prompts (kind, version),
  KEY ix_ai_prompts_active (kind, is_active),
  KEY ix_ai_prompts_created_by (created_by),
  KEY ix_ai_prompts_updated_by (updated_by),
  KEY ix_ai_prompts_deleted_by (deleted_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Prompt AI theo phiên bản, sửa trong admin';

CREATE TABLE ai_cache (
  hash         CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'sha256(loại|phiên bản prompt|model|đầu vào chuẩn hoá)',
  kind         VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  response     JSON NOT NULL,
  hits         INT UNSIGNED NOT NULL DEFAULT 0,
  created_at   INT UNSIGNED NOT NULL,
  last_hit_at  INT UNSIGNED NULL,
  expires_at   INT UNSIGNED NULL,
  PRIMARY KEY (hash),
  KEY ix_ai_cache_kind (kind, created_at),
  KEY ix_ai_cache_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Kết quả AI dùng chung cho mọi người; kind=lesson không hết hạn';

CREATE TABLE ai_requests (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id         BIGINT UNSIGNED NULL,
  kind            VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'như ai_prompts.kind, thêm pron',
  provider        VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'gemini' COMMENT 'gemini, azure_speech, …',
  prompt_id       INT UNSIGNED NULL,
  model           VARCHAR(60) CHARACTER SET ascii COLLATE ascii_bin NULL,
  context_type    VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NULL COMMENT 'card, exercise, grammar_lesson, verb',
  context_id      BIGINT UNSIGNED NULL,
  input_excerpt   VARCHAR(500) NULL COMMENT 'xoá sau 30 ngày',
  input_tokens    INT UNSIGNED NOT NULL DEFAULT 0,
  cached_tokens   INT UNSIGNED NOT NULL DEFAULT 0,
  output_tokens   INT UNSIGNED NOT NULL DEFAULT 0,
  cost_micro_usd  INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'chi phí USD × 1.000.000',
  cache_hit       TINYINT(1) NOT NULL DEFAULT 0,
  status          VARCHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'ok, error, quota, blocked',
  latency_ms      INT UNSIGNED NULL,
  created_at      INT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  KEY ix_ai_req_user (user_id, created_at),
  KEY ix_ai_req_created (created_at),
  KEY ix_ai_req_prompt (prompt_id),
  KEY ix_ai_req_context (context_type, context_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Nhật ký gọi AI: hạn mức và chi phí';

CREATE TABLE ai_conversations (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id        CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'ULID',
  user_id          BIGINT UNSIGNED NOT NULL,
  scenario         VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'free' COMMENT 'free, restaurant, visa_interview, workplace…',
  level            VARCHAR(4) CHARACTER SET ascii COLLATE ascii_bin NULL COMMENT 'A1…C1',
  context_type     VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NULL COMMENT 'card, grammar_lesson, exercise… khi mở từ nút Hỏi AI',
  context_id       BIGINT UNSIGNED NULL,
  title            VARCHAR(160) NULL,
  message_count    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  last_message_at  INT UNSIGNED NULL,
  created_at       INT UNSIGNED NOT NULL,
  updated_at       INT UNSIGNED NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ai_conv_public (public_id),
  KEY ix_ai_conv_user (user_id, last_message_at),
  KEY ix_ai_conv_context (context_type, context_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Trò chuyện AI; người dùng xoá là xoá hẳn';

CREATE TABLE ai_messages (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  conversation_id  BIGINT UNSIGNED NOT NULL,
  role             VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'user, assistant',
  content          TEXT NOT NULL,
  corrections      JSON NULL COMMENT 'lỗi AI chỉ ra trong câu người học',
  request_id       BIGINT UNSIGNED NULL COMMENT 'ai_requests.id của lượt trả lời',
  created_at       INT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  KEY ix_ai_msg_conv (conversation_id, id),
  KEY ix_ai_msg_request (request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tin nhắn trong trò chuyện AI';

CREATE TABLE user_ai_lessons (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id       BIGINT UNSIGNED NOT NULL,
  cache_hash    CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'ai_cache.hash chứa nội dung bài',
  topic_id      INT UNSIGNED NULL,
  level         VARCHAR(4) CHARACTER SET ascii COLLATE ascii_bin NULL,
  title         VARCHAR(160) NOT NULL,
  score         TINYINT UNSIGNED NULL COMMENT '0–100 phần câu hỏi',
  completed_at  INT UNSIGNED NULL,
  created_at    INT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ual_user_lesson (user_id, cache_hash),
  KEY ix_ual_user (user_id, created_at),
  KEY ix_ual_topic (topic_id),
  KEY ix_ual_cache (cache_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bài học AI của từng người';

CREATE TABLE pronunciation_attempts (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id             BIGINT UNSIGNED NOT NULL,
  card_id             BIGINT UNSIGNED NULL,
  example_id          BIGINT UNSIGNED NULL,
  lang                VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'de-DE',
  reference_text      VARCHAR(600) NOT NULL COMMENT 'câu hoặc từ phải đọc',
  provider            VARCHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'azure, browser',
  status              VARCHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'ok, empty, error, quota',
  overall_score       TINYINT UNSIGNED NULL COMMENT '0–100',
  accuracy_score      TINYINT UNSIGNED NULL,
  fluency_score       TINYINT UNSIGNED NULL,
  completeness_score  TINYINT UNSIGNED NULL,
  words               JSON NULL COMMENT 'điểm và lỗi từng từ, từng âm',
  audio_ms            INT UNSIGNED NULL COMMENT 'độ dài ghi âm; không lưu file',
  created_at          INT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  KEY ix_pron_user (user_id, created_at),
  KEY ix_pron_card (card_id),
  KEY ix_pron_example (example_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Lượt chấm phát âm; không giữ file ghi âm';

-- =====================================================================
-- 5. GÓI, ĐƠN HÀNG, THANH TOÁN
-- =====================================================================

CREATE TABLE entitlements (
  code           VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'levels, modes, ai_daily, offline, exam_prep, custom_decks',
  name           VARCHAR(120) NOT NULL,
  value_type     VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'bool, int, list',
  default_value  JSON NULL COMMENT 'giá trị cho khách chưa đăng nhập',
  description    VARCHAR(255) NULL,
  sort           SMALLINT NOT NULL DEFAULT 0,
  created_at     INT UNSIGNED NULL,
  created_by     BIGINT UNSIGNED NULL,
  updated_at     INT UNSIGNED NULL,
  updated_by     BIGINT UNSIGNED NULL,
  PRIMARY KEY (code),
  KEY ix_entitlements_created_by (created_by),
  KEY ix_entitlements_updated_by (updated_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Danh mục quyền lợi';

CREATE TABLE plans (
  id                SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code              VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'free, premium_1m, premium_12m, lifetime, seat_12m',
  name              VARCHAR(120) NOT NULL,
  description       VARCHAR(500) NULL,
  tier              TINYINT UNSIGNED NOT NULL COMMENT '1 Free, 2 Premium; khớp access_tier của nội dung',
  price             BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'đồng',
  compare_at_price  BIGINT UNSIGNED NULL COMMENT 'giá gạch',
  currency          CHAR(3) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'VND',
  period_unit       VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'none, day, month, year, lifetime',
  period_count      SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  trial_days        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  is_b2b            TINYINT(1) NOT NULL DEFAULT 0,
  is_public         TINYINT(1) NOT NULL DEFAULT 1,
  is_active         TINYINT(1) NOT NULL DEFAULT 1,
  sort              SMALLINT NOT NULL DEFAULT 0,
  created_at        INT UNSIGNED NULL,
  created_by        BIGINT UNSIGNED NULL,
  updated_at        INT UNSIGNED NULL,
  updated_by        BIGINT UNSIGNED NULL,
  deleted_at        INT UNSIGNED NULL,
  deleted_by        BIGINT UNSIGNED NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_plans_code (code),
  KEY ix_plans_created_by (created_by),
  KEY ix_plans_updated_by (updated_by),
  KEY ix_plans_deleted_by (deleted_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Gói bán';

CREATE TABLE plan_entitlements (
  plan_id           SMALLINT UNSIGNED NOT NULL,
  entitlement_code  VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  value             JSON NOT NULL,
  updated_at        INT UNSIGNED NULL,
  updated_by        BIGINT UNSIGNED NULL,
  PRIMARY KEY (plan_id, entitlement_code),
  KEY ix_plan_ent_code (entitlement_code),
  KEY ix_plan_ent_updated_by (updated_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Quyền lợi của từng gói';

CREATE TABLE subscriptions (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id          BIGINT UNSIGNED NULL,
  organization_id  BIGINT UNSIGNED NULL,
  plan_id          SMALLINT UNSIGNED NOT NULL,
  status           VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'trialing, active, expired, canceled, revoked',
  source           VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'order, trial, admin, coupon, referral, organization',
  order_id         BIGINT UNSIGNED NULL,
  starts_at        INT UNSIGNED NOT NULL,
  ends_at          INT UNSIGNED NULL COMMENT 'NULL = trọn đời',
  canceled_at      INT UNSIGNED NULL,
  note             VARCHAR(255) NULL,
  created_at       INT UNSIGNED NULL,
  created_by       BIGINT UNSIGNED NULL COMMENT 'nhân viên cấp tay, nếu có',
  updated_at       INT UNSIGNED NULL,
  updated_by       BIGINT UNSIGNED NULL,
  PRIMARY KEY (id),
  KEY ix_subs_user (user_id, status, ends_at),
  KEY ix_subs_org (organization_id),
  KEY ix_subs_plan (plan_id),
  KEY ix_subs_order (order_id),
  KEY ix_subs_ends (status, ends_at),
  KEY ix_subs_created_by (created_by),
  KEY ix_subs_updated_by (updated_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Thời hạn dùng gói; mua thêm thì nối tiếp';

CREATE TABLE orders (
  id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id          CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  code               VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'ghi vào nội dung chuyển khoản, vd LB2611250042',
  user_id            BIGINT UNSIGNED NULL,
  organization_id    BIGINT UNSIGNED NULL,
  plan_id            SMALLINT UNSIGNED NOT NULL,
  quantity           SMALLINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'số chỗ học (B2B)',
  subtotal           BIGINT UNSIGNED NOT NULL,
  discount           BIGINT UNSIGNED NOT NULL DEFAULT 0,
  total              BIGINT UNSIGNED NOT NULL,
  currency           CHAR(3) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'VND',
  coupon_id          INT UNSIGNED NULL,
  status             VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'pending' COMMENT 'pending, paid, expired, canceled, refunded, partially_refunded',
  payment_method     VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NULL COMMENT 'bank_qr, momo, zalopay, paypal, manual',
  expires_at         INT UNSIGNED NULL,
  paid_at            INT UNSIGNED NULL,
  invoice_requested  TINYINT(1) NOT NULL DEFAULT 0,
  billing_info       JSON NULL COMMENT 'tên, mã số thuế, địa chỉ, email nhận hoá đơn',
  meta               JSON NULL COMMENT 'utm, thiết bị',
  created_at         INT UNSIGNED NULL,
  created_by         BIGINT UNSIGNED NULL,
  updated_at         INT UNSIGNED NULL,
  updated_by         BIGINT UNSIGNED NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_orders_public (public_id),
  UNIQUE KEY uq_orders_code (code),
  KEY ix_orders_user (user_id, created_at),
  KEY ix_orders_org (organization_id),
  KEY ix_orders_plan (plan_id),
  KEY ix_orders_coupon (coupon_id),
  KEY ix_orders_status (status, expires_at),
  KEY ix_orders_created_by (created_by),
  KEY ix_orders_updated_by (updated_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Đơn mua gói';

CREATE TABLE payments (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id          BIGINT UNSIGNED NULL COMMENT 'NULL = tiền vào chưa khớp đơn',
  gateway           VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'sepay, payos, momo, zalopay, paypal, manual',
  gateway_txn_id    VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  amount            BIGINT UNSIGNED NOT NULL,
  currency          CHAR(3) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'VND',
  status            VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'succeeded, unmatched, failed, refunded',
  transfer_content  VARCHAR(255) NULL COMMENT 'nội dung chuyển khoản',
  payer_info        JSON NULL,
  raw               JSON NULL,
  paid_at           INT UNSIGNED NULL,
  matched_by        BIGINT UNSIGNED NULL COMMENT 'nhân viên khớp tay',
  created_at        INT UNSIGNED NULL,
  updated_at        INT UNSIGNED NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_payments_txn (gateway, gateway_txn_id),
  KEY ix_payments_order (order_id),
  KEY ix_payments_status (status, created_at),
  KEY ix_payments_matched_by (matched_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Giao dịch tiền vào, mỗi giao dịch ghi một lần';

CREATE TABLE payment_events (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  gateway       VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  event_key     VARCHAR(120) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'id sự kiện của cổng hoặc sha256 của payload',
  signature_ok  TINYINT(1) NOT NULL DEFAULT 0,
  payload       JSON NOT NULL,
  status        VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'received' COMMENT 'received, processed, ignored, failed',
  error         VARCHAR(500) NULL,
  received_at   INT UNSIGNED NOT NULL,
  processed_at  INT UNSIGNED NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_payment_events (gateway, event_key),
  KEY ix_payment_events_status (status, received_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Webhook nhận từ cổng thanh toán, chống xử lý trùng';

CREATE TABLE refunds (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id      BIGINT UNSIGNED NOT NULL,
  payment_id    BIGINT UNSIGNED NULL,
  amount        BIGINT UNSIGNED NOT NULL,
  reason        VARCHAR(500) NULL,
  status        VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'pending' COMMENT 'pending, done, failed',
  processed_at  INT UNSIGNED NULL,
  created_at    INT UNSIGNED NULL,
  created_by    BIGINT UNSIGNED NULL,
  updated_at    INT UNSIGNED NULL,
  updated_by    BIGINT UNSIGNED NULL,
  PRIMARY KEY (id),
  KEY ix_refunds_order (order_id),
  KEY ix_refunds_payment (payment_id),
  KEY ix_refunds_created_by (created_by),
  KEY ix_refunds_updated_by (updated_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Hoàn tiền';

CREATE TABLE coupons (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code             VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'lưu viết hoa',
  kind             VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'percent, fixed, days',
  value            INT UNSIGNED NOT NULL,
  plan_ids         JSON NULL COMMENT 'NULL = mọi gói',
  max_redemptions  INT UNSIGNED NULL,
  per_user_limit   SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  redeemed_count   INT UNSIGNED NOT NULL DEFAULT 0,
  starts_at        INT UNSIGNED NULL,
  ends_at          INT UNSIGNED NULL,
  is_active        TINYINT(1) NOT NULL DEFAULT 1,
  created_at       INT UNSIGNED NULL,
  created_by       BIGINT UNSIGNED NULL,
  updated_at       INT UNSIGNED NULL,
  updated_by       BIGINT UNSIGNED NULL,
  deleted_at       INT UNSIGNED NULL,
  deleted_by       BIGINT UNSIGNED NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_coupons_code (code),
  KEY ix_coupons_created_by (created_by),
  KEY ix_coupons_updated_by (updated_by),
  KEY ix_coupons_deleted_by (deleted_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Mã giảm giá';

CREATE TABLE coupon_redemptions (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  coupon_id   INT UNSIGNED NOT NULL,
  user_id     BIGINT UNSIGNED NOT NULL,
  order_id    BIGINT UNSIGNED NULL,
  created_at  INT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  KEY ix_coupon_red (coupon_id, user_id),
  KEY ix_coupon_red_user (user_id),
  KEY ix_coupon_red_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Lượt dùng mã giảm giá';

CREATE TABLE invoices (
  id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id           BIGINT UNSIGNED NOT NULL,
  number             VARCHAR(40) NULL COMMENT 'số hoá đơn điện tử',
  buyer              JSON NOT NULL,
  amount_before_tax  BIGINT UNSIGNED NOT NULL,
  tax_rate           DECIMAL(5,2) NULL,
  tax_amount         BIGINT UNSIGNED NOT NULL DEFAULT 0,
  total              BIGINT UNSIGNED NOT NULL,
  status             VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'pending' COMMENT 'pending, issued, failed, canceled',
  provider           VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NULL,
  provider_ref       VARCHAR(100) NULL,
  pdf_path           VARCHAR(255) NULL,
  issued_at          INT UNSIGNED NULL,
  created_at         INT UNSIGNED NULL,
  created_by         BIGINT UNSIGNED NULL,
  updated_at         INT UNSIGNED NULL,
  updated_by         BIGINT UNSIGNED NULL,
  PRIMARY KEY (id),
  KEY ix_invoices_order (order_id),
  KEY ix_invoices_status (status),
  KEY ix_invoices_created_by (created_by),
  KEY ix_invoices_updated_by (updated_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Hoá đơn điện tử';

CREATE TABLE referrals (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  referrer_id  BIGINT UNSIGNED NOT NULL,
  referred_id  BIGINT UNSIGNED NOT NULL,
  status       VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'signed_up' COMMENT 'signed_up, converted, rewarded, rejected',
  reward_days  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  rewarded_at  INT UNSIGNED NULL,
  created_at   INT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_referrals_referred (referred_id),
  KEY ix_referrals_referrer (referrer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Giới thiệu bạn bè';

-- =====================================================================
-- 6. ĐỘNG LỰC VÀ THÔNG BÁO
-- =====================================================================

CREATE TABLE achievements (
  id              SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code            VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  name_vi         VARCHAR(120) NOT NULL,
  description_vi  VARCHAR(255) NULL,
  icon            VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NULL,
  rule            JSON NOT NULL COMMENT 'vd {"type":"streak","value":7}',
  coins_reward    INT UNSIGNED NOT NULL DEFAULT 0,
  is_active       TINYINT(1) NOT NULL DEFAULT 1,
  sort            SMALLINT NOT NULL DEFAULT 0,
  created_at      INT UNSIGNED NULL,
  created_by      BIGINT UNSIGNED NULL,
  updated_at      INT UNSIGNED NULL,
  updated_by      BIGINT UNSIGNED NULL,
  deleted_at      INT UNSIGNED NULL,
  deleted_by      BIGINT UNSIGNED NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_achievements_code (code),
  KEY ix_achievements_created_by (created_by),
  KEY ix_achievements_updated_by (updated_by),
  KEY ix_achievements_deleted_by (deleted_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Huy hiệu';

CREATE TABLE user_achievements (
  user_id         BIGINT UNSIGNED NOT NULL,
  achievement_id  SMALLINT UNSIGNED NOT NULL,
  earned_at       INT UNSIGNED NOT NULL,
  PRIMARY KEY (user_id, achievement_id),
  KEY ix_user_ach_achievement (achievement_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Huy hiệu đã nhận';

CREATE TABLE leaderboard_weeks (
  week_start  INT UNSIGNED NOT NULL COMMENT '00:00 thứ Hai theo giờ Việt Nam',
  user_id     BIGINT UNSIGNED NOT NULL,
  score       INT UNSIGNED NOT NULL,
  rank_no     INT UNSIGNED NOT NULL,
  PRIMARY KEY (week_start, user_id),
  KEY ix_lb_rank (week_start, rank_no),
  KEY ix_lb_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Chốt bảng xếp hạng mỗi tuần';

CREATE TABLE push_subscriptions (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id           BIGINT UNSIGNED NOT NULL,
  endpoint_hash     CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'sha256 của endpoint',
  endpoint          TEXT NOT NULL,
  public_key        VARCHAR(255) NULL,
  auth_token        VARCHAR(255) NULL,
  content_encoding  VARCHAR(20) NULL,
  user_agent        VARCHAR(255) NULL,
  created_at        INT UNSIGNED NULL,
  updated_at        INT UNSIGNED NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_push_endpoint (endpoint_hash),
  KEY ix_push_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Đăng ký nhận web push';

CREATE TABLE announcements (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title       VARCHAR(160) NOT NULL,
  body        TEXT NULL,
  level       VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'info' COMMENT 'info, warning',
  audience    JSON NULL COMMENT 'vd {"tier":[1]}',
  starts_at   INT UNSIGNED NULL,
  ends_at     INT UNSIGNED NULL,
  is_active   TINYINT(1) NOT NULL DEFAULT 1,
  created_at  INT UNSIGNED NULL,
  created_by  BIGINT UNSIGNED NULL,
  updated_at  INT UNSIGNED NULL,
  updated_by  BIGINT UNSIGNED NULL,
  deleted_at  INT UNSIGNED NULL,
  deleted_by  BIGINT UNSIGNED NULL,
  PRIMARY KEY (id),
  KEY ix_announcements_active (is_active, starts_at, ends_at),
  KEY ix_announcements_created_by (created_by),
  KEY ix_announcements_updated_by (updated_by),
  KEY ix_announcements_deleted_by (deleted_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Thông báo chung (banner)';

-- =====================================================================
-- 7. HỖ TRỢ, QUY TRÌNH NỘI DUNG, CẤU HÌNH
-- =====================================================================

CREATE TABLE content_reports (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id      BIGINT UNSIGNED NULL,
  target_type  VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'card, card_example, exercise, grammar_lesson, verb, audio, image',
  target_id    BIGINT UNSIGNED NOT NULL,
  reason       VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'wrong_meaning, wrong_image, typo, audio, grammar, other',
  message      VARCHAR(1000) NULL,
  status       VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'open' COMMENT 'open, accepted, fixed, rejected',
  assigned_to  BIGINT UNSIGNED NULL,
  resolution   VARCHAR(500) NULL,
  handled_at   INT UNSIGNED NULL,
  created_at   INT UNSIGNED NULL,
  created_by   BIGINT UNSIGNED NULL,
  updated_at   INT UNSIGNED NULL,
  updated_by   BIGINT UNSIGNED NULL,
  PRIMARY KEY (id),
  KEY ix_reports_status (status, created_at),
  KEY ix_reports_target (target_type, target_id),
  KEY ix_reports_user (user_id),
  KEY ix_reports_assignee (assigned_to),
  KEY ix_reports_created_by (created_by),
  KEY ix_reports_updated_by (updated_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Báo lỗi nội dung từ người học';

CREATE TABLE support_tickets (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id        BIGINT UNSIGNED NULL,
  email          VARCHAR(191) NULL,
  subject        VARCHAR(200) NOT NULL,
  category       VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NULL COMMENT 'payment, account, content, bug, other',
  status         VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'open' COMMENT 'open, pending, solved, closed',
  priority       TINYINT UNSIGNED NOT NULL DEFAULT 2,
  assigned_to    BIGINT UNSIGNED NULL,
  last_reply_at  INT UNSIGNED NULL,
  created_at     INT UNSIGNED NULL,
  created_by     BIGINT UNSIGNED NULL,
  updated_at     INT UNSIGNED NULL,
  updated_by     BIGINT UNSIGNED NULL,
  deleted_at     INT UNSIGNED NULL,
  deleted_by     BIGINT UNSIGNED NULL,
  PRIMARY KEY (id),
  KEY ix_tickets_status (status, priority),
  KEY ix_tickets_user (user_id),
  KEY ix_tickets_assignee (assigned_to),
  KEY ix_tickets_created_by (created_by),
  KEY ix_tickets_updated_by (updated_by),
  KEY ix_tickets_deleted_by (deleted_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Yêu cầu hỗ trợ';

CREATE TABLE support_messages (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ticket_id    BIGINT UNSIGNED NOT NULL,
  author_id    BIGINT UNSIGNED NULL,
  is_staff     TINYINT(1) NOT NULL DEFAULT 0,
  body         TEXT NOT NULL,
  attachments  JSON NULL,
  created_at   INT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  KEY ix_support_msg_ticket (ticket_id, created_at),
  KEY ix_support_msg_author (author_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tin nhắn trong yêu cầu hỗ trợ';

CREATE TABLE content_revisions (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  target_type  VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  target_id    BIGINT UNSIGNED NOT NULL,
  version      INT UNSIGNED NOT NULL,
  snapshot     JSON NOT NULL,
  change_note  VARCHAR(255) NULL,
  status       VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'draft, in_review, approved, rejected, published',
  author_id    BIGINT UNSIGNED NULL,
  reviewer_id  BIGINT UNSIGNED NULL,
  created_at   INT UNSIGNED NOT NULL,
  reviewed_at  INT UNSIGNED NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_revisions (target_type, target_id, version),
  KEY ix_revisions_status (status, created_at),
  KEY ix_revisions_author (author_id),
  KEY ix_revisions_reviewer (reviewer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Lịch sử phiên bản nội dung và quy trình duyệt';

CREATE TABLE content_imports (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id      BIGINT UNSIGNED NULL,
  file_name    VARCHAR(255) NOT NULL,
  kind         VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'cards, exercises, grammar, verbs, lernbuch_db',
  status       VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'queued' COMMENT 'queued, running, done, failed',
  dry_run      TINYINT(1) NOT NULL DEFAULT 1,
  stats        JSON NULL COMMENT 'số dòng thêm, sửa, bỏ qua',
  errors       JSON NULL,
  started_at   INT UNSIGNED NULL,
  finished_at  INT UNSIGNED NULL,
  created_at   INT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  KEY ix_imports_status (status),
  KEY ix_imports_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Lượt nhập Excel, CSV hoặc DB Lernbuch cũ';

CREATE TABLE pages (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug          VARCHAR(120) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  kind          VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'legal, help, landing',
  title         VARCHAR(200) NOT NULL,
  body          MEDIUMTEXT NOT NULL,
  version       VARCHAR(20) NOT NULL DEFAULT '1',
  status        VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'draft',
  published_at  INT UNSIGNED NULL,
  created_at    INT UNSIGNED NULL,
  created_by    BIGINT UNSIGNED NULL,
  updated_at    INT UNSIGNED NULL,
  updated_by    BIGINT UNSIGNED NULL,
  deleted_at    INT UNSIGNED NULL,
  deleted_by    BIGINT UNSIGNED NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pages_slug (slug),
  KEY ix_pages_created_by (created_by),
  KEY ix_pages_updated_by (updated_by),
  KEY ix_pages_deleted_by (deleted_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Trang nội dung: điều khoản, quyền riêng tư, trợ giúp';

CREATE TABLE settings (
  name        VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  value       JSON NULL,
  is_secret   TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = giá trị đã mã hoá',
  updated_at  INT UNSIGNED NULL,
  updated_by  BIGINT UNSIGNED NULL,
  PRIMARY KEY (name),
  KEY ix_settings_updated_by (updated_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cấu hình hệ thống sửa trong admin';

-- =====================================================================
-- 8. TRUNG TÂM VÀ LỚP HỌC (B2B, giai đoạn 5)
-- =====================================================================

CREATE TABLE organizations (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name           VARCHAR(200) NOT NULL,
  slug           VARCHAR(120) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  tax_code       VARCHAR(20) NULL,
  contact_name   VARCHAR(120) NULL,
  contact_email  VARCHAR(191) NULL,
  contact_phone  VARCHAR(20) NULL,
  seats          INT UNSIGNED NOT NULL DEFAULT 0,
  seats_used     INT UNSIGNED NOT NULL DEFAULT 0,
  status         VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'active',
  created_at     INT UNSIGNED NULL,
  created_by     BIGINT UNSIGNED NULL,
  updated_at     INT UNSIGNED NULL,
  updated_by     BIGINT UNSIGNED NULL,
  deleted_at     INT UNSIGNED NULL,
  deleted_by     BIGINT UNSIGNED NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_org_slug (slug),
  KEY ix_org_created_by (created_by),
  KEY ix_org_updated_by (updated_by),
  KEY ix_org_deleted_by (deleted_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Trung tâm, trường (khách B2B)';

CREATE TABLE organization_members (
  organization_id  BIGINT UNSIGNED NOT NULL,
  user_id          BIGINT UNSIGNED NOT NULL,
  role             VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'owner, admin, teacher, student',
  joined_at        INT UNSIGNED NOT NULL,
  PRIMARY KEY (organization_id, user_id),
  KEY ix_org_members_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Thành viên trung tâm';

CREATE TABLE classrooms (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  organization_id  BIGINT UNSIGNED NULL,
  teacher_id       BIGINT UNSIGNED NOT NULL,
  course_id        INT UNSIGNED NOT NULL,
  name             VARCHAR(160) NOT NULL,
  join_code        VARCHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  starts_on        INT UNSIGNED NULL,
  ends_on          INT UNSIGNED NULL,
  status           VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'active',
  created_at       INT UNSIGNED NULL,
  created_by       BIGINT UNSIGNED NULL,
  updated_at       INT UNSIGNED NULL,
  updated_by       BIGINT UNSIGNED NULL,
  deleted_at       INT UNSIGNED NULL,
  deleted_by       BIGINT UNSIGNED NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_classrooms_code (join_code),
  KEY ix_classrooms_org (organization_id),
  KEY ix_classrooms_teacher (teacher_id),
  KEY ix_classrooms_course (course_id),
  KEY ix_classrooms_created_by (created_by),
  KEY ix_classrooms_updated_by (updated_by),
  KEY ix_classrooms_deleted_by (deleted_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Lớp học';

CREATE TABLE classroom_members (
  classroom_id  BIGINT UNSIGNED NOT NULL,
  user_id       BIGINT UNSIGNED NOT NULL,
  role          VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'student' COMMENT 'teacher, student',
  joined_at     INT UNSIGNED NOT NULL,
  PRIMARY KEY (classroom_id, user_id),
  KEY ix_classroom_members_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Học viên trong lớp';

CREATE TABLE assignments (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  classroom_id  BIGINT UNSIGNED NOT NULL,
  title         VARCHAR(200) NOT NULL,
  target_type   VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'topic, exercise_set, grammar_lesson, deck',
  target_id     BIGINT UNSIGNED NOT NULL,
  due_at        INT UNSIGNED NULL,
  min_score     TINYINT UNSIGNED NULL COMMENT '% tối thiểu để đạt',
  created_at    INT UNSIGNED NULL,
  created_by    BIGINT UNSIGNED NULL,
  updated_at    INT UNSIGNED NULL,
  updated_by    BIGINT UNSIGNED NULL,
  deleted_at    INT UNSIGNED NULL,
  deleted_by    BIGINT UNSIGNED NULL,
  PRIMARY KEY (id),
  KEY ix_assignments_class (classroom_id, due_at),
  KEY ix_assignments_target (target_type, target_id),
  KEY ix_assignments_created_by (created_by),
  KEY ix_assignments_updated_by (updated_by),
  KEY ix_assignments_deleted_by (deleted_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bài giao cho lớp';

CREATE TABLE assignment_progress (
  assignment_id  BIGINT UNSIGNED NOT NULL,
  user_id        BIGINT UNSIGNED NOT NULL,
  status         VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'todo' COMMENT 'todo, in_progress, done',
  score          TINYINT UNSIGNED NULL,
  completed_at   INT UNSIGNED NULL,
  updated_at     INT UNSIGNED NULL,
  PRIMARY KEY (assignment_id, user_id),
  KEY ix_assign_progress_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tiến độ bài giao của từng học viên';

-- =====================================================================
-- 9. BẢNG CỦA LARAVEL VÀ GÓI ĐI KÈM (giữ dạng gốc, không đổi sang int timestamp)
--    Khi dùng Redis cho cache, phiên, hàng đợi thì sessions, cache, jobs chỉ để dự phòng.
-- =====================================================================

CREATE TABLE password_reset_tokens (
  email       VARCHAR(255) NOT NULL,
  token       VARCHAR(255) NOT NULL,
  created_at  TIMESTAMP NULL,
  PRIMARY KEY (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Laravel: mã đặt lại mật khẩu';

CREATE TABLE sessions (
  id             VARCHAR(255) NOT NULL,
  user_id        BIGINT UNSIGNED NULL,
  ip_address     VARCHAR(45) NULL,
  user_agent     TEXT NULL,
  payload        LONGTEXT NOT NULL,
  last_activity  INT NOT NULL,
  PRIMARY KEY (id),
  KEY sessions_user_id_index (user_id),
  KEY sessions_last_activity_index (last_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Laravel: phiên đăng nhập (dự phòng)';

CREATE TABLE cache (
  `key`       VARCHAR(255) NOT NULL,
  value       MEDIUMTEXT NOT NULL,
  expiration  INT NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Laravel: cache (dự phòng)';

CREATE TABLE cache_locks (
  `key`       VARCHAR(255) NOT NULL,
  owner       VARCHAR(255) NOT NULL,
  expiration  INT NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Laravel: khoá cache (dự phòng)';

CREATE TABLE jobs (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  queue         VARCHAR(255) NOT NULL,
  payload       LONGTEXT NOT NULL,
  attempts      TINYINT UNSIGNED NOT NULL,
  reserved_at   INT UNSIGNED NULL,
  available_at  INT UNSIGNED NOT NULL,
  created_at    INT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  KEY jobs_queue_index (queue)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Laravel: hàng đợi (dự phòng)';

CREATE TABLE job_batches (
  id              VARCHAR(255) NOT NULL,
  name            VARCHAR(255) NOT NULL,
  total_jobs      INT NOT NULL,
  pending_jobs    INT NOT NULL,
  failed_jobs     INT NOT NULL,
  failed_job_ids  LONGTEXT NOT NULL,
  options         MEDIUMTEXT NULL,
  cancelled_at    INT NULL,
  created_at      INT NOT NULL,
  finished_at     INT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Laravel: lô job';

CREATE TABLE failed_jobs (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  uuid        VARCHAR(255) NOT NULL,
  connection  TEXT NOT NULL,
  queue       TEXT NOT NULL,
  payload     LONGTEXT NOT NULL,
  exception   LONGTEXT NOT NULL,
  failed_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY failed_jobs_uuid_unique (uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Laravel: job lỗi';

CREATE TABLE personal_access_tokens (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tokenable_type  VARCHAR(255) NOT NULL,
  tokenable_id    BIGINT UNSIGNED NOT NULL,
  name            TEXT NOT NULL,
  token           VARCHAR(64) NOT NULL,
  abilities       TEXT NULL,
  last_used_at    TIMESTAMP NULL,
  expires_at      TIMESTAMP NULL,
  created_at      TIMESTAMP NULL,
  updated_at      TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY personal_access_tokens_token_unique (token),
  KEY personal_access_tokens_tokenable_type_tokenable_id_index (tokenable_type, tokenable_id),
  KEY personal_access_tokens_expires_at_index (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Sanctum: token cho app di động';

CREATE TABLE notifications (
  id               CHAR(36) NOT NULL,
  type             VARCHAR(255) NOT NULL,
  notifiable_type  VARCHAR(255) NOT NULL,
  notifiable_id    BIGINT UNSIGNED NOT NULL,
  data             TEXT NOT NULL,
  read_at          TIMESTAMP NULL,
  created_at       TIMESTAMP NULL,
  updated_at       TIMESTAMP NULL,
  PRIMARY KEY (id),
  KEY notifications_notifiable_type_notifiable_id_index (notifiable_type, notifiable_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Laravel: thông báo trong app (xoá sau 6 tháng)';

CREATE TABLE features (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name        VARCHAR(255) NOT NULL,
  scope       VARCHAR(255) NOT NULL,
  value       TEXT NOT NULL,
  created_at  TIMESTAMP NULL,
  updated_at  TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY features_name_scope_unique (name, scope)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Pennant: bật/tắt tính năng theo nhóm người dùng';
