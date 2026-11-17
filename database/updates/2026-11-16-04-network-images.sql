-- KUEDU2IMPACT: ใส่รูปจริงให้หน้าเครือข่ายวิจัยนานาชาติ (network-international.php) โดยไม่ต้องอัปโหลดทีละรูป
--   รูปอยู่ในโฟลเดอร์ assets/images/network/ ของเว็บ (อัปโหลดโฟลเดอร์นี้ขึ้นเซิร์ฟเวอร์ก่อน)
--   1) รูปการ์ด 10 สถาบัน: network_partners.image (ใช้เมื่อ image_id ว่าง ถ้าภายหลังเลือกรูปใหม่ใน admin รูปใน admin จะใช้แทน)
--   2) แถบภาพกิจกรรม 21 รูป: network_photos
-- phpMyAdmin → e2imedu → Import รันหลัง 2026-11-16-01-network-tags.sql (ต้องมีคอลัมน์ image ใน network_partners)
-- รันซ้ำได้: การ์ดเขียนทับเฉพาะสถาบันที่ยังไม่มีรูป ภาพกิจกรรมเพิ่มเฉพาะไฟล์ที่ยังไม่มีในตาราง
SET NAMES utf8mb4;

-- ตาราง network_photos (เหมือน 2026-11-16-03-network-photos.sql เผื่อยังไม่ได้ Import)
CREATE TABLE IF NOT EXISTS network_photos (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  image_id    INT UNSIGNED NULL COMMENT 'รูป: เลข id ในตาราง media',
  image       VARCHAR(255) NULL COMMENT 'รูปแบบเดิม (path) ใช้เมื่อ image_id ว่าง',
  caption     VARCHAR(300) NOT NULL COMMENT 'คำบรรยายภาพ ใช้เป็นข้อความแทนภาพสำหรับโปรแกรมอ่านหน้าจอ',
  is_public   TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = แสดง, 0 = ซ่อน',
  sort_order  INT NOT NULL DEFAULT 0 COMMENT 'ลำดับ (น้อยขึ้นก่อน)',
  CONSTRAINT fk_network_photos_image FOREIGN KEY (image_id) REFERENCES media (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ภาพกิจกรรมกับเครือข่ายวิจัยนานาชาติ (แถบรูปเลื่อนต่อเนื่อง)';

-- 1) รูปการ์ดสถาบัน
UPDATE network_partners SET image = 'assets/images/network/konkuk-07.webp' WHERE institution = 'Konkuk University' AND image_id IS NULL AND (image IS NULL OR image = '');
UPDATE network_partners SET image = 'assets/images/network/chiba-03.webp' WHERE institution = 'Chiba University' AND image_id IS NULL AND (image IS NULL OR image = '');
UPDATE network_partners SET image = 'assets/images/network/kyoai-01.webp' WHERE institution = 'Kyoai Gakuen University' AND image_id IS NULL AND (image IS NULL OR image = '');
UPDATE network_partners SET image = 'assets/images/network/hiroshima-02.webp' WHERE institution = 'Hiroshima University' AND image_id IS NULL AND (image IS NULL OR image = '');
UPDATE network_partners SET image = 'assets/images/network/ussh-01.webp' WHERE institution = 'University of Social Sciences and Humanities, Vietnam National University, Hanoi (USSH)' AND image_id IS NULL AND (image IS NULL OR image = '');
UPDATE network_partners SET image = 'assets/images/network/ibaraki-03.webp' WHERE institution = 'Ibaraki University' AND image_id IS NULL AND (image IS NULL OR image = '');
UPDATE network_partners SET image = 'assets/images/network/pnu-02.webp' WHERE institution = 'Philippine Normal University (PNU)' AND image_id IS NULL AND (image IS NULL OR image = '');
UPDATE network_partners SET image = 'assets/images/network/uwgb-03.webp' WHERE institution = 'University of Wisconsin–Green Bay (UWGB)' AND image_id IS NULL AND (image IS NULL OR image = '');
UPDATE network_partners SET image = 'assets/images/network/fudan-01.webp' WHERE institution = 'Fudan University' AND image_id IS NULL AND (image IS NULL OR image = '');
UPDATE network_partners SET image = 'assets/images/network/ndhu-01.webp' WHERE institution = 'National Dong Hwa University (NDHU)' AND image_id IS NULL AND (image IS NULL OR image = '');

-- 2) แถบภาพกิจกรรม
INSERT INTO network_photos (image, caption, is_public, sort_order) SELECT 'assets/images/network/konkuk-02.webp', 'นักศึกษาบัณฑิตศึกษาจาก Konkuk University เยือนคณะศึกษาศาสตร์ มก.', 1, 0 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM network_photos WHERE image = 'assets/images/network/konkuk-02.webp');
INSERT INTO network_photos (image, caption, is_public, sort_order) SELECT 'assets/images/network/konkuk-04.webp', 'นิสิตคณะศึกษาศาสตร์แลกเปลี่ยนระยะสั้นที่ Konkuk University เกาหลีใต้ พฤศจิกายน 2568', 1, 1 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM network_photos WHERE image = 'assets/images/network/konkuk-04.webp');
INSERT INTO network_photos (image, caption, is_public, sort_order) SELECT 'assets/images/network/konkuk-06.webp', 'นิสิตปริญญาเอกสาขาปฐมวัยศึกษาแลกเปลี่ยนเรียนรู้ที่ Konkuk University', 1, 2 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM network_photos WHERE image = 'assets/images/network/konkuk-06.webp');
INSERT INTO network_photos (image, caption, is_public, sort_order) SELECT 'assets/images/network/chiba-02.webp', 'นิสิตบัณฑิตศึกษาในโครงการ TWINCLE ที่ Chiba University ญี่ปุ่น', 1, 3 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM network_photos WHERE image = 'assets/images/network/chiba-02.webp');
INSERT INTO network_photos (image, caption, is_public, sort_order) SELECT 'assets/images/network/chiba-05.webp', 'นักศึกษาจาก Chiba University ทำกิจกรรมที่คณะศึกษาศาสตร์ มก.', 1, 4 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM network_photos WHERE image = 'assets/images/network/chiba-05.webp');
INSERT INTO network_photos (image, caption, is_public, sort_order) SELECT 'assets/images/network/kyoai-03.webp', 'นิสิตคณะศึกษาศาสตร์ทำกิจกรรมที่ Kyoai Gakuen University ญี่ปุ่น', 1, 5 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM network_photos WHERE image = 'assets/images/network/kyoai-03.webp');
INSERT INTO network_photos (image, caption, is_public, sort_order) SELECT 'assets/images/network/kyoai-05.webp', 'นักศึกษาจาก Kyoai Gakuen University แลกเปลี่ยนที่คณะศึกษาศาสตร์ มก.', 1, 6 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM network_photos WHERE image = 'assets/images/network/kyoai-05.webp');
INSERT INTO network_photos (image, caption, is_public, sort_order) SELECT 'assets/images/network/hiroshima-01.webp', 'กิจกรรมแลกเปลี่ยนโครงการ Campus Asia กับนักศึกษาจาก Hiroshima University', 1, 7 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM network_photos WHERE image = 'assets/images/network/hiroshima-01.webp');
INSERT INTO network_photos (image, caption, is_public, sort_order) SELECT 'assets/images/network/ussh-02.webp', 'นักศึกษาจาก USSH เวียดนาม ร่วมกิจกรรมแลกเปลี่ยนที่คณะศึกษาศาสตร์ ธันวาคม 2568', 1, 8 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM network_photos WHERE image = 'assets/images/network/ussh-02.webp');
INSERT INTO network_photos (image, caption, is_public, sort_order) SELECT 'assets/images/network/ibaraki-01.webp', 'คณะศึกษาศาสตร์ต้อนรับคณาจารย์และนักศึกษาจาก Ibaraki University', 1, 9 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM network_photos WHERE image = 'assets/images/network/ibaraki-01.webp');
INSERT INTO network_photos (image, caption, is_public, sort_order) SELECT 'assets/images/network/ibaraki-02.webp', 'กิจกรรมแลกเปลี่ยนทางวัฒนธรรมกับนักศึกษาจาก Ibaraki University', 1, 10 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM network_photos WHERE image = 'assets/images/network/ibaraki-02.webp');
INSERT INTO network_photos (image, caption, is_public, sort_order) SELECT 'assets/images/network/pnu-01.webp', 'นิสิตจาก Philippine Normal University ฝึกประสบการณ์วิชาชีพที่โรงเรียนเครือข่าย', 1, 11 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM network_photos WHERE image = 'assets/images/network/pnu-01.webp');
INSERT INTO network_photos (image, caption, is_public, sort_order) SELECT 'assets/images/network/uwgb-01.webp', 'นักศึกษาจาก University of Wisconsin–Green Bay ฝึกประสบการณ์วิชาชีพที่โรงเรียนสาธิต มก.', 1, 12 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM network_photos WHERE image = 'assets/images/network/uwgb-01.webp');
INSERT INTO network_photos (image, caption, is_public, sort_order) SELECT 'assets/images/network/uwgb-04.webp', 'บูธกิจกรรมของนักศึกษาจาก University of Wisconsin–Green Bay ที่โรงเรียนสาธิต มก.', 1, 13 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM network_photos WHERE image = 'assets/images/network/uwgb-04.webp');
INSERT INTO network_photos (image, caption, is_public, sort_order) SELECT 'assets/images/network/fudan-02.webp', 'คณะศึกษาศาสตร์และโรงเรียนสาธิต มก. ต้อนรับนักศึกษาจาก Fudan University', 1, 14 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM network_photos WHERE image = 'assets/images/network/fudan-02.webp');
INSERT INTO network_photos (image, caption, is_public, sort_order) SELECT 'assets/images/network/fudan-03.webp', 'นักศึกษาจาก Fudan University ร่วมกิจกรรมแลกเปลี่ยนทางวิชาการและวัฒนธรรม', 1, 15 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM network_photos WHERE image = 'assets/images/network/fudan-03.webp');
INSERT INTO network_photos (image, caption, is_public, sort_order) SELECT 'assets/images/network/fudan-05.webp', 'นักศึกษาจาก Fudan University หน้าอาคารคณะศึกษาศาสตร์ มก.', 1, 16 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM network_photos WHERE image = 'assets/images/network/fudan-05.webp');
INSERT INTO network_photos (image, caption, is_public, sort_order) SELECT 'assets/images/network/ndhu-03.webp', 'คณะศึกษาศาสตร์ต้อนรับคณาจารย์จาก National Dong Hwa University ไต้หวัน', 1, 17 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM network_photos WHERE image = 'assets/images/network/ndhu-03.webp');
INSERT INTO network_photos (image, caption, is_public, sort_order) SELECT 'assets/images/network/ndhu-05.webp', 'คณาจารย์คณะศึกษาศาสตร์บรรยายให้นิสิตบัณฑิตศึกษาที่ National Dong Hwa University', 1, 18 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM network_photos WHERE image = 'assets/images/network/ndhu-05.webp');
INSERT INTO network_photos (image, caption, is_public, sort_order) SELECT 'assets/images/network/asten-02.webp', 'คณาจารย์คณะศึกษาศาสตร์ในกิจกรรมเครือข่าย AsTEN', 1, 19 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM network_photos WHERE image = 'assets/images/network/asten-02.webp');
INSERT INTO network_photos (image, caption, is_public, sort_order) SELECT 'assets/images/network/asten-03.webp', 'กิจกรรมทางวิชาการร่วมกับสถาบันในเครือข่าย AsTEN', 1, 20 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM network_photos WHERE image = 'assets/images/network/asten-03.webp');

-- ตรวจผล: การ์ดที่มีรูปควรมี 10 แถว และภาพกิจกรรม 21 แถว
SELECT institution, image FROM network_partners WHERE image IS NOT NULL OR image_id IS NOT NULL ORDER BY sort_order, id;
SELECT COUNT(*) AS photos FROM network_photos WHERE is_public = 1;
