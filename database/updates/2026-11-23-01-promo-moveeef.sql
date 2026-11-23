-- KUEDU2IMPACT: แบนเนอร์ "คลังความรู้อาชีพ 5,280 รายการ เพื่อโรงเรียนห่างไกล" ในสไลด์ประชาสัมพันธ์หน้าแรก
--   ลิงก์ไปที่ innovation-detail.php?id=moveeef
--   รูป assets/images/promo-moveeef-career-library.webp (1600 x 800) ต้องอัปโหลดขึ้นเซิร์ฟเวอร์ก่อน
--   ขึ้นเป็นสไลด์แรก (sort_order ต่ำกว่าแถวเดิมทั้งหมด)
-- phpMyAdmin → e2imedu → Import ใช้ได้ทั้ง MySQL 8 และ MariaDB 10.x
-- รันซ้ำได้: เพิ่มเฉพาะเมื่อยังไม่มีแถวที่ใช้รูปนี้
SET NAMES utf8mb4;

SET @first := (SELECT COALESCE(MIN(sort_order), 0) - 1 FROM home_items WHERE type = 'promo');

INSERT INTO home_items (type, title, item_date, label, image_id, image, url, is_public, sort_order)
SELECT 'promo',
       'คลังความรู้อาชีพ 5,280 รายการ เพื่อโรงเรียนห่างไกล ทั้งวีดิโอ อีบุ๊ก เกมการเรียนรู้ และสื่อวิทยุ',
       '2026-11-23', 'นวัตกรรม', NULL,
       'assets/images/promo-moveeef-career-library.webp',
       'https://www.edu2impact.edu.ku.ac.th/innovation-detail.php?id=moveeef',
       1, @first
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM home_items WHERE type = 'promo' AND image = 'assets/images/promo-moveeef-career-library.webp');
