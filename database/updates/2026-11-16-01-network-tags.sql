-- KUEDU2IMPACT: แท็กรูปแบบความร่วมมือ สรุปหนึ่งประโยค ผู้ประสานงาน และรูปประกอบการ์ด ของเครือข่ายวิจัยนานาชาติ (network-international.php)
--   network_partners.tags    = slug คั่นด้วยจุลภาค เช่น exchange,research (ชุดแท็กอยู่ใน config/network-tags.php)
--   network_partners.summary = ประโยคเดียวที่บอกว่าทำอะไรร่วมกัน
--   network_partners.people  = ชื่อผู้ประสานงานหรือนักวิชาการ คั่นด้วยจุลภาค
--   network_partners.image_id = รูปประกอบการ์ด (เลข id ในตาราง media) ต้องรัน 2026-10-27-01-structure.sql มาก่อน
-- และซ่อนแถบ "รูปแบบความร่วมมือ" กับ "คุณค่าที่เกิดกับหลักสูตรและนิสิต" (network_highlights) ซึ่งหน้าเว็บเลิกแสดงแล้ว
-- ตารางและข้อมูลเดิมของ network_highlights ยังอยู่ ไม่ได้ลบ
-- phpMyAdmin → e2imedu → Import ใช้ได้ทั้ง MySQL 8 และ MariaDB 10.x
-- รันซ้ำได้: เพิ่มคอลัมน์เฉพาะที่ยังไม่มี ไม่ลบและไม่แก้ข้อมูลใน network_partners
-- รันหลัง 2026-11-06-01-network.sql และ 2026-11-06-02-network-highlights.sql
-- ต้องอัปโหลดโค้ดชุดใหม่ก่อนหรือพร้อมกับไฟล์นี้ (โค้ดเก่าอ่านคอลัมน์ใหม่ไม่ได้แต่ไม่พัง)
SET NAMES utf8mb4;
SET @db = DATABASE();

-- 1. คอลัมน์ใหม่ใน network_partners (MySQL ไม่มี ADD COLUMN IF NOT EXISTS จึงตรวจจาก information_schema ก่อนทุกคอลัมน์)
SET @s = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'network_partners' AND COLUMN_NAME = 'tags') = 0,
  'ALTER TABLE network_partners ADD COLUMN tags VARCHAR(200) CHARACTER SET ascii COLLATE ascii_bin NULL COMMENT ''แท็กรูปแบบความร่วมมือ: slug คั่นด้วยจุลภาค (ต้องมีใน config/network-tags.php)'' AFTER institution_th', 'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'network_partners' AND COLUMN_NAME = 'summary') = 0,
  'ALTER TABLE network_partners ADD COLUMN summary VARCHAR(200) NULL COMMENT ''สรุปหนึ่งประโยค ว่าทำอะไรร่วมกัน'' AFTER tags', 'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'network_partners' AND COLUMN_NAME = 'people') = 0,
  'ALTER TABLE network_partners ADD COLUMN people VARCHAR(300) NULL COMMENT ''ผู้ประสานงานหรือนักวิชาการ คั่นด้วยจุลภาค'' AFTER summary', 'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- รูปประกอบของการ์ดสถาบัน: เลข id ในตาราง media (แบบเดียวกับ community_projects) ถ้าไม่มีรูป การ์ดเป็นพื้นม่วงเข้ม
SET @s = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'network_partners' AND COLUMN_NAME = 'image_id') = 0,
  'ALTER TABLE network_partners ADD COLUMN image_id INT UNSIGNED NULL COMMENT ''รูปประกอบการ์ด: เลข id ในตาราง media'' AFTER url', 'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'network_partners' AND COLUMN_NAME = 'image') = 0,
  'ALTER TABLE network_partners ADD COLUMN image VARCHAR(255) NULL COMMENT ''รูปแบบเดิม (path) ใช้เมื่อ image_id ว่าง'' AFTER image_id', 'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = IF((SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'network_partners' AND CONSTRAINT_NAME = 'fk_network_partners_image') = 0,
  'ALTER TABLE network_partners ADD CONSTRAINT fk_network_partners_image FOREIGN KEY (image_id) REFERENCES media (id) ON DELETE RESTRICT ON UPDATE CASCADE', 'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- 2. ซ่อนข้อความสองแถบเดิมใต้แผนที่โลก
UPDATE network_highlights SET is_public = 0;

-- ถ้าใช้ผู้ใช้ฐานข้อมูลตัวที่สองสำหรับหน้า admin (ดูท้ายไฟล์ 2026-10-27-04-admin.sql) สิทธิ์อ่านและเขียน
-- บนตาราง network_partners ที่ให้ไว้แล้วครอบคลุมคอลัมน์ใหม่ทั้งหมด ไม่ต้องเพิ่มสิทธิ์

-- ตรวจผล: ควรเห็นคอลัมน์ tags, summary, people, image_id, image
SHOW COLUMNS FROM network_partners;
