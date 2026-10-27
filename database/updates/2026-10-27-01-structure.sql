-- Round 13 (1/3): ตารางและคอลัมน์ใหม่ เพื่อเก็บทุกอย่างของโพสต์ไว้ใน MySQL รวมรูปภาพ
--
-- วิธีรัน: phpMyAdmin > คลิกฐานข้อมูล e2imedu ทางซ้าย > แท็บ SQL > วางไฟล์นี้ทั้งไฟล์ > Go
--          (หรือแท็บ Import > เลือกไฟล์นี้ > Import)
-- ต้องรันก่อนไฟล์ 2026-10-27-02-media-*.sql และ 2026-10-27-03-content.sql
--
-- ปลอดภัย: ไม่มี DROP TABLE ไม่ลบแถว ไม่แก้ข้อความที่แก้ไว้ใน MySQL แล้ว รันซ้ำกี่ครั้งก็ได้
-- ต้องใช้ MySQL 5.7 ขึ้นไป หรือ MariaDB 10.2 ขึ้นไป (ดูได้ที่หน้าแรกของ phpMyAdmin หัวข้อ Database server หรือรัน SELECT VERSION();)
SET NAMES utf8mb4;

-- ---------------------------------------------------------------------------------------------
-- 1. media: รูปภาพและไฟล์ PDF ทั้งหมดของโพสต์ เก็บเป็นข้อมูลไบนารีในคอลัมน์ data
--    เว็บแสดงไฟล์ผ่าน file.php?id=<id> ส่วน sha256 และ bytes ฐานข้อมูลคำนวณเองทุกครั้งที่ data เปลี่ยน
--    จึงไม่ต้องกรอกเอง และเบราว์เซอร์จะโหลดรูปใหม่ทันทีที่เปลี่ยนไฟล์
CREATE TABLE IF NOT EXISTS media (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'เลขรูป นำไปใส่ใน posts.image_id หรือ {"media": เลขนี้} ในเนื้อหา',
  filename    VARCHAR(255) NOT NULL COMMENT 'ชื่อไฟล์ (ห้ามซ้ำ) เช่น workshop-2570.webp รูปที่ย้ายมาจากเว็บเดิมใช้ path เดิม assets/images/…',
  mime        VARCHAR(50)  NULL COMMENT 'ชนิดไฟล์ เว้นว่างได้ ระบบตรวจเอง: image/webp, image/jpeg, image/png, application/pdf',
  width       SMALLINT UNSIGNED NULL COMMENT 'กว้าง (px) เว้นว่างได้',
  height      SMALLINT UNSIGNED NULL COMMENT 'สูง (px) เว้นว่างได้',
  alt_default VARCHAR(400) NULL COMMENT 'คำอธิบายรูปสำหรับผู้ใช้โปรแกรมอ่านหน้าจอ (ใช้เมื่อโพสต์ไม่ได้ใส่ alt เอง)',
  og_of       INT UNSIGNED NULL COMMENT 'แถวนี้เป็นภาพแชร์ 1200x630 ของรูปเลขนี้ (ระบบใช้เอง ปล่อยว่างสำหรับรูปทั่วไป)',
  data        LONGBLOB NOT NULL COMMENT 'ตัวไฟล์ ใน phpMyAdmin กด Insert แล้วเลือกไฟล์ในช่องนี้ (ไม่เกิน 1 MB ต่อรูปจะดีที่สุด)',
  sha256      CHAR(64) AS (SHA2(data, 256)) STORED COMMENT 'คำนวณอัตโนมัติ ห้ามแก้',
  bytes       INT UNSIGNED AS (LENGTH(data)) STORED COMMENT 'ขนาดไฟล์ (ไบต์) คำนวณอัตโนมัติ',
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_media_filename (filename),
  KEY ix_media_og (og_of)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='รูปและ PDF ของโพสต์ แสดงผ่าน file.php?id=';

-- ---------------------------------------------------------------------------------------------
-- 2. posts: คอลัมน์ที่ยังขาด (audience, subjects, features, ไฟล์ดาวน์โหลด) และรูปปกแบบอ้างเลขรูป
--    MySQL ไม่มี ADD COLUMN IF NOT EXISTS จึงตรวจจาก information_schema ก่อนทุกคอลัมน์
SET @db = DATABASE();

SET @s = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'posts' AND COLUMN_NAME = 'image_id') = 0,
  'ALTER TABLE posts ADD COLUMN image_id INT UNSIGNED NULL COMMENT ''รูปปก: เลข id ในตาราง media (ใช้แทนคอลัมน์ image)'' AFTER image', 'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'posts' AND COLUMN_NAME = 'audience') = 0,
  'ALTER TABLE posts ADD COLUMN audience LONGTEXT NULL COMMENT ''JSON array ของข้อความ "เหมาะกับ" เช่น ["ครูวิทยาศาสตร์","ครู ม.ต้น"]'' AFTER refs', 'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'posts' AND COLUMN_NAME = 'subjects') = 0,
  'ALTER TABLE posts ADD COLUMN subjects LONGTEXT NULL COMMENT ''JSON array ของข้อความ "ใช้ได้กับกลุ่มสาระ"'' AFTER audience', 'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'posts' AND COLUMN_NAME = 'features') = 0,
  'ALTER TABLE posts ADD COLUMN features LONGTEXT NULL COMMENT ''JSON array ของข้อความ "ความสามารถหลัก"'' AFTER subjects', 'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'posts' AND COLUMN_NAME = 'download_id') = 0,
  'ALTER TABLE posts ADD COLUMN download_id INT UNSIGNED NULL COMMENT ''PDF ฉบับเต็ม: เลข id ในตาราง media (ต้องเป็น PDF)'' AFTER tool_label', 'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'posts' AND COLUMN_NAME = 'download_label') = 0,
  'ALTER TABLE posts ADD COLUMN download_label VARCHAR(150) NULL COMMENT ''ข้อความบนปุ่มดาวน์โหลด เว้นว่าง = ดาวน์โหลด PDF'' AFTER download_id', 'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- คำอธิบายภาษาไทยของคอลัมน์เดิม (ชนิดข้อมูลเหมือนเดิมทุกคอลัมน์ เปลี่ยนแค่ COMMENT)
ALTER TABLE posts
  MODIFY kind       VARCHAR(20)  NOT NULL COMMENT 'ประเภท: research, innovations, workshops, media, news',
  MODIFY slug       VARCHAR(120) NOT NULL COMMENT 'ชื่อในลิงก์ ?id= ใช้ a-z 0-9 และ - เท่านั้น ห้ามซ้ำในประเภทเดียวกัน (เปลี่ยนแล้วลิงก์เดิมเสีย)',
  MODIFY title      VARCHAR(400) NOT NULL COMMENT 'ชื่อเรื่อง',
  MODIFY summary    TEXT NULL COMMENT 'สรุปสั้น แสดงบนการ์ดและใต้ชื่อเรื่อง',
  MODIFY body       LONGTEXT NULL COMMENT 'เนื้อหา: JSON array ย่อหน้าเป็นข้อความ รูปเป็น {"media": เลขรูป, "alt": "...", "caption": "..."}',
  MODIFY refs       LONGTEXT NULL COMMENT 'อ้างอิง: JSON array ของข้อความ หรือว่าง',
  MODIFY image      VARCHAR(255) NULL COMMENT 'รูปปกแบบเดิม (path) ใช้เมื่อ image_id ว่าง',
  MODIFY post_date  DATE NULL COMMENT 'วันที่ (ปี ค.ศ.) เว้นว่างได้',
  MODIFY author     VARCHAR(255) NULL COMMENT 'ผู้จัดทำ คั่นหลายคนด้วย , และ "และ"',
  MODIFY category   VARCHAR(100) NULL COMMENT 'หมวด (นวัตกรรมต้องตรงกับชื่อใน config/innovation-categories.php)',
  MODIFY tool_url   VARCHAR(500) NULL COMMENT 'ลิงก์เครื่องมือ https://… หรือ apps/<ชื่อ>/',
  MODIFY tool_label VARCHAR(150) NULL COMMENT 'ข้อความบนปุ่มเครื่องมือ',
  MODIFY featured   TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = แนะนำ (ขึ้นก่อน)',
  MODIFY is_public  TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = เผยแพร่, 0 = ซ่อน (ใช้แทนการลบ)',
  MODIFY sort_order INT NOT NULL DEFAULT 0 COMMENT 'ลำดับ (น้อยขึ้นก่อน เมื่อวันที่เท่ากัน)';

-- ถ้าช่อง JSON พิมพ์ผิด (ลืม " หรือ ,) phpMyAdmin จะไม่ยอมบันทึก แทนที่เนื้อหาจะหายไปจากหน้าเว็บเงียบๆ
-- (MySQL 8.0.16+ และ MariaDB ตรวจจริง; MySQL 5.7 รับคำสั่งแต่ไม่ตรวจ)
SET @s = IF((SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'posts' AND CONSTRAINT_NAME = 'chk_posts_json') = 0,
  'ALTER TABLE posts ADD CONSTRAINT chk_posts_json CHECK ((body IS NULL OR JSON_VALID(body)) AND (refs IS NULL OR JSON_VALID(refs)) AND (audience IS NULL OR JSON_VALID(audience)) AND (subjects IS NULL OR JSON_VALID(subjects)) AND (features IS NULL OR JSON_VALID(features)))', 'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ป้องกันการลบรูปที่โพสต์ยังใช้อยู่ และทำให้ phpMyAdmin แสดงรายการเลขรูปให้เลือก
SET @s = IF((SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'posts' AND CONSTRAINT_NAME = 'fk_posts_image') = 0,
  'ALTER TABLE posts ADD CONSTRAINT fk_posts_image FOREIGN KEY (image_id) REFERENCES media (id) ON DELETE RESTRICT ON UPDATE CASCADE', 'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = IF((SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'posts' AND CONSTRAINT_NAME = 'fk_posts_download') = 0,
  'ALTER TABLE posts ADD CONSTRAINT fk_posts_download FOREIGN KEY (download_id) REFERENCES media (id) ON DELETE RESTRICT ON UPDATE CASCADE', 'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ---------------------------------------------------------------------------------------------
-- 3. events: คอลัมน์ที่ data/events.json มีแต่ตารางยังไม่มี
SET @s = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'events' AND COLUMN_NAME = 'slug') = 0,
  'ALTER TABLE events ADD COLUMN slug VARCHAR(120) NULL COMMENT ''รหัสกิจกรรม (id ใน events.json) เว้นว่างได้'' AFTER id', 'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'events' AND COLUMN_NAME = 'featured') = 0,
  'ALTER TABLE events ADD COLUMN featured TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''1 = แนะนำ'' AFTER event_date', 'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'events' AND COLUMN_NAME = 'is_public') = 0,
  'ALTER TABLE events ADD COLUMN is_public TINYINT(1) NOT NULL DEFAULT 1 COMMENT ''1 = แสดง, 0 = ซ่อน'' AFTER featured', 'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ---------------------------------------------------------------------------------------------
-- 4. home_items: "ประชาสัมพันธ์" (promo) และ "Scoop" (video) บนหน้าแรก (เดิมอยู่ใน config/home.php)
CREATE TABLE IF NOT EXISTS home_items (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  type       ENUM('promo','video') NOT NULL COMMENT 'promo = แบนเนอร์ประชาสัมพันธ์, video = คลิป Scoop',
  title      VARCHAR(400) NOT NULL COMMENT 'ชื่อ (promo: อ่านออกเสียงแทนข้อความในรูป)',
  item_date  DATE NULL COMMENT 'วันที่ เว้นว่างได้',
  label      VARCHAR(100) NULL COMMENT 'ป้าย (promo) ปัจจุบันไม่แสดง',
  image_id   INT UNSIGNED NULL COMMENT 'รูป: เลข id ในตาราง media (promo ใช้ภาพ 2:1 เช่น 1920x960; video เว้นว่าง = ใช้ภาพจาก YouTube)',
  image      VARCHAR(255) NULL COMMENT 'รูปแบบเดิม (path) ใช้เมื่อ image_id ว่าง',
  url        VARCHAR(500) NULL COMMENT 'promo: ลิงก์เมื่อคลิก (หน้าในเว็บ หรือ https://…) ใส่ # = คลิกไม่ได้',
  youtube    VARCHAR(500) NULL COMMENT 'video: ลิงก์ YouTube เต็ม',
  is_public  TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = แสดง, 0 = ซ่อน',
  sort_order INT NOT NULL DEFAULT 0 COMMENT 'ลำดับในสไลด์ (น้อยขึ้นก่อน)',
  KEY ix_home_items (type, is_public, sort_order),
  CONSTRAINT fk_home_items_image FOREIGN KEY (image_id) REFERENCES media (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ประชาสัมพันธ์และ Scoop บนหน้าแรก';

-- ---------------------------------------------------------------------------------------------
-- 5. shop_items: สินค้าในหน้าร้านค้า (เดิมอยู่ใน data/shop.json)
CREATE TABLE IF NOT EXISTS shop_items (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug       VARCHAR(120) NOT NULL COMMENT 'รหัสสินค้า a-z 0-9 -',
  title      VARCHAR(400) NOT NULL COMMENT 'ชื่อสินค้า',
  summary    TEXT NULL COMMENT 'คำอธิบายสั้น',
  image_id   INT UNSIGNED NULL COMMENT 'รูป: เลข id ในตาราง media',
  image      VARCHAR(255) NULL COMMENT 'รูปแบบเดิม (path) ใช้เมื่อ image_id ว่าง',
  price      VARCHAR(100) NULL COMMENT 'ราคา: ตัวเลข เช่น 1200 (แสดง ฿1,200) หรือข้อความ เช่น สอบถามราคา',
  order_url  VARCHAR(500) NULL COMMENT 'ลิงก์สั่งซื้อ https://… เว้นว่าง = ไปหน้าติดต่อเรา',
  is_public  TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = แสดง, 0 = ซ่อน',
  sort_order INT NOT NULL DEFAULT 0 COMMENT 'ลำดับ (น้อยขึ้นก่อน)',
  UNIQUE KEY uq_shop_slug (slug),
  CONSTRAINT fk_shop_items_image FOREIGN KEY (image_id) REFERENCES media (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='สินค้าในหน้าร้านค้า';

-- ---------------------------------------------------------------------------------------------
-- 6. thai_breaks: จุดตัดคำภาษาไทยของแต่ละย่อหน้า (เดิมอยู่ใน data/thai-breaks.json)
--    สร้างโดย tools/segment_thai.py ไม่ต้องแก้เอง ย่อหน้าที่ไม่มีข้อมูลยังแสดงได้ปกติ แค่ตัดบรรทัดตามเบราว์เซอร์
CREATE TABLE IF NOT EXISTS thai_breaks (
  hash    CHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY COMMENT 'SHA-1 ของข้อความย่อหน้า',
  offsets TEXT NOT NULL COMMENT 'JSON array ตำแหน่งตัดคำ'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='การตัดคำไทย สร้างโดย tools/segment_thai.py';

-- ---------------------------------------------------------------------------------------------
-- 7. มุมมองช่วยทำงานใน phpMyAdmin (อ่านอย่างเดียว เปิดดูได้เหมือนตาราง)
--    ถ้าบรรทัดนี้ error เพราะบัญชีไม่มีสิทธิ์ CREATE VIEW ข้ามได้ ทุกอย่างด้านบนสำเร็จแล้ว
CREATE OR REPLACE VIEW v_media AS
  SELECT m.id, m.filename, m.mime, m.width, m.height, ROUND(m.bytes / 1024) AS kb, m.og_of, m.alt_default, m.created_at
  FROM media m ORDER BY m.id;

CREATE OR REPLACE VIEW v_posts AS
  SELECT p.id, p.kind, p.slug, p.title, p.is_public, p.featured, p.post_date, p.image_id,
         COALESCE(m.filename, p.image) AS cover, p.download_id, p.sort_order, p.updated_at
  FROM posts p LEFT JOIN media m ON m.id = p.image_id
  ORDER BY p.kind, p.sort_order, p.id;

-- ตรวจผล: ควรเห็นตาราง media, home_items, shop_items, thai_breaks
SHOW TABLES;
