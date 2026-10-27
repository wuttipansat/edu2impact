-- Round 15: ตัวนับจำนวนการเข้าชมโพสต์ เก็บใน MySQL (เดิมเก็บในไฟล์ storage/visits.json ซึ่งโฮสต์เขียนไม่ได้)
--
-- วิธีรัน: phpMyAdmin > คลิกฐานข้อมูล e2imedu > แท็บ Import > เลือกไฟล์นี้ > Import
-- ปลอดภัย: ไม่มี DROP ไม่แตะตารางเดิม รันซ้ำกี่ครั้งก็ได้ ใช้ได้กับ MySQL 5.7+ และ MariaDB 10.2+
--
-- ไม่เก็บ IP หรือ User-Agent: ตาราง post_view_seen เก็บเฉพาะตัวย่อ (HMAC) ของผู้เข้าชมในวันนั้น
-- และถูกล้างของวันก่อนทิ้งโดยอัตโนมัติ
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS post_views (
    page_key   VARCHAR(191) NOT NULL COMMENT 'รหัสหน้าโพสต์ รูปแบบ ประเภท/slug เช่น research/pck-canvas',
    total      INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'จำนวนการเข้าชมทั้งหมด (1 คนต่อ 1 โพสต์ต่อ 1 วัน)',
    updated_at DATETIME DEFAULT NULL COMMENT 'เวลาที่นับครั้งล่าสุด',
    PRIMARY KEY (page_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ยอดเข้าชมรวมของแต่ละโพสต์';

CREATE TABLE IF NOT EXISTS post_view_days (
    page_key VARCHAR(191) NOT NULL COMMENT 'รหัสหน้าโพสต์ เหมือน post_views.page_key',
    day      DATE NOT NULL COMMENT 'วันที่ (เวลาไทย)',
    views    INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'จำนวนการเข้าชมในวันนั้น เก็บย้อนหลัง 90 วัน',
    PRIMARY KEY (page_key, day)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ยอดเข้าชมรายวันของแต่ละโพสต์';

CREATE TABLE IF NOT EXISTS post_view_seen (
    hash CHAR(20) NOT NULL COMMENT 'ตัวย่อ HMAC ของ (IP + User-Agent + โพสต์ + วันที่ + ความลับ) อ่านย้อนกลับเป็นตัวบุคคลไม่ได้',
    day  DATE NOT NULL COMMENT 'วันที่ (เวลาไทย) แถวของวันก่อนถูกลบอัตโนมัติ',
    PRIMARY KEY (hash),
    KEY idx_post_view_seen_day (day)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ใครเข้าโพสต์ไหนแล้วในวันนี้ (กันนับซ้ำ)';

-- ---------------------------------------------------------------------------------------------
-- หมายเหตุ: ตัวนับทำงานตอนมีคนเปิดหน้าโพสต์ จึงต้องให้ "ผู้ใช้ DB ของเว็บสาธารณะ" เขียน 3 ตารางนี้ได้
-- (ถ้ามีแค่สิทธิ์อ่าน ตัวนับจะเงียบและหลังบ้านจะขึ้นคำเตือนสีแดง)
-- ทำใน cPanel > MySQL Databases > Add User To Database ติ๊ก SELECT, INSERT, UPDATE, DELETE
-- หรือใน phpMyAdmin (บัญชีที่มีสิทธิ์ GRANT) รันคำสั่งนี้ โดยเปลี่ยนชื่อผู้ใช้ให้ตรง:
--   GRANT SELECT, INSERT, UPDATE, DELETE ON e2imedu.post_views     TO 'e2imedu_read'@'localhost';
--   GRANT SELECT, INSERT, UPDATE, DELETE ON e2imedu.post_view_days TO 'e2imedu_read'@'localhost';
--   GRANT SELECT, INSERT, UPDATE, DELETE ON e2imedu.post_view_seen TO 'e2imedu_read'@'localhost';
-- ถ้าไม่อยากให้ผู้ใช้อ่านอย่างเดียวเขียนได้ ให้สร้างผู้ใช้ตัวนับแยก แล้วใส่ใน private/database.php:
--   'counter_user' => '...', 'counter_pass' => '...'
-- (ผู้ใช้ตัวนับต้องมีสิทธิ์เฉพาะ 3 ตารางข้างบน)
-- ผู้ใช้หลังบ้าน (admin_user) ต้องมีสิทธิ์ UPDATE, INSERT ที่ post_views และ post_view_days ด้วย
-- เพื่อให้ยอดตามไปเมื่อเปลี่ยนที่อยู่ลิงก์ (slug) ของโพสต์ ถ้าไม่มีสิทธิ์ การบันทึกโพสต์ยังทำงานปกติ แต่ยอดจะไม่ย้ายตาม
--   GRANT INSERT, UPDATE ON e2imedu.post_views     TO 'e2imedu_admin'@'localhost';
--   GRANT INSERT, UPDATE ON e2imedu.post_view_days TO 'e2imedu_admin'@'localhost';
