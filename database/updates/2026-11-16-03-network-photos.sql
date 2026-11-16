-- KUEDU2IMPACT: ภาพกิจกรรมกับเครือข่าย แถบรูปที่เลื่อนต่อเนื่องใต้ "เครือข่ายวิจัยและความร่วมมือทั้งหมด" (network-international.php)
--   network_photos = หนึ่งแถวต่อหนึ่งรูป รูปอยู่ในตาราง media (อัปโหลดหรือเลือกจากคลังรูปที่ admin/network-photos.php)
-- phpMyAdmin → e2imedu → Import ใช้ได้ทั้ง MySQL 8 และ MariaDB 10.x
-- รันซ้ำได้: สร้างตารางเมื่อยังไม่มี ตารางเริ่มต้นว่าง หน้าเว็บไม่แสดงแถบนี้จนกว่าจะมีรูปที่แสดงบนเว็บอย่างน้อยหนึ่งรูป
-- ต้องรัน 2026-10-27-01-structure.sql ก่อน เพราะรูปอ้างตาราง media
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS network_photos (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  image_id    INT UNSIGNED NULL COMMENT 'รูป: เลข id ในตาราง media',
  image       VARCHAR(255) NULL COMMENT 'รูปแบบเดิม (path) ใช้เมื่อ image_id ว่าง',
  caption     VARCHAR(300) NOT NULL COMMENT 'คำบรรยายภาพ ใช้เป็นข้อความแทนภาพสำหรับโปรแกรมอ่านหน้าจอ',
  is_public   TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = แสดง, 0 = ซ่อน',
  sort_order  INT NOT NULL DEFAULT 0 COMMENT 'ลำดับ (น้อยขึ้นก่อน)',
  CONSTRAINT fk_network_photos_image FOREIGN KEY (image_id) REFERENCES media (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ภาพกิจกรรมกับเครือข่ายวิจัยนานาชาติ (แถบรูปเลื่อนต่อเนื่อง)';

-- ถ้าใช้ผู้ใช้ฐานข้อมูลตัวที่สองสำหรับหน้า admin (ดูท้ายไฟล์ 2026-10-27-04-admin.sql) ให้เพิ่มสิทธิ์อ่านและเขียน
-- (SELECT, INSERT, UPDATE และสิทธิ์ลบแถว) บนตาราง network_photos ให้ผู้ใช้นั้นด้วย ผู้ใช้ตัวที่อ่านอย่างเดียวได้ SELECT จาก e2imedu.* อยู่แล้ว

-- ตรวจผล: ควรเห็นตาราง network_photos
SHOW TABLES LIKE 'network_photos';
