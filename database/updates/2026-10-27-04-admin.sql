-- Round 14: ตารางและคอลัมน์สำหรับหน้า admin (หลังบ้านที่ซ่อนอยู่)
--
-- วิธีรัน: phpMyAdmin > คลิกฐานข้อมูล e2imedu > แท็บ Import > เลือกไฟล์นี้ > Import
-- ต้องรันหลัง 2026-10-27-01-structure.sql (ต้องมีตาราง media และ posts ใหม่แล้ว)
--
-- ปลอดภัย: ไม่มี DROP ไม่ลบแถว ไม่แก้ข้อมูลเดิม รันซ้ำกี่ครั้งก็ได้
-- ใช้ได้กับ MySQL 5.7+ และ MariaDB 10.2+
SET NAMES utf8mb4;

-- ---------------------------------------------------------------------------------------------
-- 1. ตารางบัญชีผู้ใช้ (เหมือน database/auth-schema.sql สร้างเฉพาะที่ยังไม่มี ผู้ใช้เดิมไม่ถูกแตะ)
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(200) NOT NULL,
    email VARCHAR(254) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    department VARCHAR(200) DEFAULT NULL,
    researcher_id VARCHAR(100) DEFAULT NULL,
    role ENUM('researcher','reviewer','executive','admin') NOT NULL DEFAULT 'researcher',
    status ENUM('pending','active','suspended') NOT NULL DEFAULT 'pending',
    email_verified_at DATETIME DEFAULT NULL,
    last_login_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS auth_rate_limits (
    bucket_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
    attempts BIGINT UNSIGNED NOT NULL DEFAULT 0,
    expires_at BIGINT UNSIGNED NOT NULL,
    KEY idx_auth_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. บทบาท editor: แก้โพสต์ รูป หน้าแรก กิจกรรม ร้านค้าได้ ส่วน admin ทำได้ทั้งหมด (ตั้งค่าเว็บ ผู้ใช้ สำรองข้อมูล)
ALTER TABLE users
  MODIFY role ENUM('researcher','reviewer','executive','admin','editor') NOT NULL DEFAULT 'researcher'
  COMMENT 'admin = ทำได้ทุกอย่าง, editor = จัดการเนื้อหา, ที่เหลือใช้กับระบบบัญชีในอนาคต';

-- ---------------------------------------------------------------------------------------------
-- 3. คอลัมน์ใหม่ (ตรวจจาก information_schema ก่อนเพิ่มทุกคอลัมน์)
SET @db = DATABASE();

SET @s = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'posts' AND COLUMN_NAME = 'updated_by') = 0,
  'ALTER TABLE posts ADD COLUMN updated_by INT UNSIGNED NULL COMMENT ''ผู้แก้ไขล่าสุด (id ในตาราง users) หน้า admin ใส่ให้เอง'' AFTER updated_at', 'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'media' AND COLUMN_NAME = 'original_name') = 0,
  'ALTER TABLE media ADD COLUMN original_name VARCHAR(255) NULL COMMENT ''ชื่อไฟล์เดิมตอนอัปโหลด (แสดงเท่านั้น)'' AFTER filename', 'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'media' AND COLUMN_NAME = 'uploaded_by') = 0,
  'ALTER TABLE media ADD COLUMN uploaded_by INT UNSIGNED NULL COMMENT ''ผู้อัปโหลด (id ในตาราง users)'' AFTER created_at', 'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ---------------------------------------------------------------------------------------------
-- 4. ประวัติการแก้โพสต์: เก็บสถานะของโพสต์ก่อนการบันทึกทุกครั้ง กู้คืนได้จากหน้า admin
CREATE TABLE IF NOT EXISTS post_revisions (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  post_id    INT UNSIGNED NOT NULL COMMENT 'id ของโพสต์ในตาราง posts',
  kind       VARCHAR(20)  NOT NULL COMMENT 'ประเภทของโพสต์ ณ ตอนนั้น',
  slug       VARCHAR(120) NOT NULL COMMENT 'slug ณ ตอนนั้น',
  action     VARCHAR(20)  NOT NULL COMMENT 'create, update, visibility, restore',
  snapshot   LONGTEXT     NOT NULL COMMENT 'JSON: ทุกช่องของโพสต์ + งานที่เกี่ยวข้อง',
  user_id    INT UNSIGNED NULL COMMENT 'ผู้บันทึก',
  user_name  VARCHAR(200) NULL COMMENT 'ชื่อผู้บันทึก ณ ตอนนั้น',
  created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_revisions_post (post_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ประวัติการแก้โพสต์ ห้ามลบ';

-- 5. บันทึกการใช้งานหลังบ้าน: ใคร ทำอะไร กับอะไร เมื่อไหร่ (ไม่เก็บ IP และไม่เก็บรหัสผ่าน)
CREATE TABLE IF NOT EXISTS admin_log (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NULL,
  user_name  VARCHAR(200) NULL,
  action     VARCHAR(40)  NOT NULL COMMENT 'เช่น post.update, media.upload, login',
  target     VARCHAR(190) NULL COMMENT 'แถวที่เกี่ยวข้อง เช่น posts#12',
  detail     TEXT NULL,
  created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_admin_log_time (created_at),
  KEY ix_admin_log_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='บันทึกการใช้งานหน้า admin';

-- 6. ค่าเล็กๆ ที่หน้า admin ต้องจำ เช่น วันที่สำรองข้อมูลล่าสุด
CREATE TABLE IF NOT EXISTS admin_meta (
  meta_key   VARCHAR(50) NOT NULL PRIMARY KEY,
  meta_value TEXT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ค่าที่หน้า admin ใช้';

-- ตรวจผล: ควรเห็น admin_log, admin_meta, post_revisions, users
SHOW TABLES;

-- ---------------------------------------------------------------------------------------------
-- (ไม่บังคับ แต่แนะนำ) สร้างผู้ใช้ฐานข้อมูลตัวที่สองสำหรับหน้า admin ให้ตัวเว็บสาธารณะยังอ่านได้อย่างเดียว
-- บน cPanel: MySQL Databases > Add New User แล้ว Add User To Database ติ๊กเฉพาะสิทธิ์ด้านล่าง
-- (ถ้า hosting ให้เลือกได้แค่ "ALL PRIVILEGES" ก็ใช้ได้ แต่ปลอดภัยน้อยกว่า)
--   ทุกตาราง:                            SELECT
--   posts, post_links, media, home_items, shop_items, events, site_settings,
--   thai_breaks, users, auth_rate_limits: SELECT, INSERT, UPDATE, DELETE
--   post_revisions, admin_log, admin_meta: SELECT, INSERT, UPDATE
-- ถ้าสร้างเองด้วย SQL (เปลี่ยนชื่อและรหัสผ่านก่อนรัน):
--   CREATE USER 'e2imedu_admin'@'localhost' IDENTIFIED BY 'รหัสผ่านยาวๆ';
--   GRANT SELECT ON e2imedu.* TO 'e2imedu_admin'@'localhost';
--   GRANT INSERT, UPDATE, DELETE ON e2imedu.posts TO 'e2imedu_admin'@'localhost';
--   (ทำซ้ำกับ post_links, media, home_items, shop_items, events, site_settings, thai_breaks, users, auth_rate_limits)
--   GRANT INSERT, UPDATE ON e2imedu.post_revisions TO 'e2imedu_admin'@'localhost';
--   (ทำซ้ำกับ admin_log, admin_meta)
