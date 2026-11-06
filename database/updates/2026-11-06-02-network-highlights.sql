-- KUEDU2IMPACT: แถบ "รูปแบบความร่วมมือ" และ "คุณค่าที่เกิดกับหลักสูตรและนิสิต" ใต้แผนที่โลกในหน้า network-international.php
--   network_highlights = ข้อความสั้นพร้อมไอคอน kind 'model' = รูปแบบความร่วมมือ, 'value' = คุณค่าที่เกิดกับหลักสูตรและนิสิต
-- phpMyAdmin → e2imedu → Import ใช้ได้ทั้ง MySQL 8 และ MariaDB 10.x
-- รันซ้ำได้: สร้างตารางเมื่อยังไม่มี และใส่ข้อมูลเริ่มต้น 11 แถวเฉพาะตอนที่ตารางยังว่าง (ไม่แก้ไม่ลบข้อมูลที่ทีมแก้ไว้)
-- รันหลัง 2026-11-06-01-network.sql ไม่ต้องใช้ตารางอื่น แก้ข้อความได้ที่ admin/network-highlights.php
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS network_highlights (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kind        VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'model = รูปแบบความร่วมมือ, value = คุณค่าที่เกิดกับหลักสูตรและนิสิต',
  title_th    VARCHAR(300) NOT NULL COMMENT 'ข้อความภาษาไทย',
  title_en    VARCHAR(300) NULL COMMENT 'ข้อความภาษาอังกฤษ (ถ้ามี)',
  icon        VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'ชื่อไอคอน ต้องมีใน includes/network-icons.php',
  is_public   TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = แสดง, 0 = ซ่อน',
  sort_order  INT NOT NULL DEFAULT 0 COMMENT 'ลำดับในกลุ่ม kind เดียวกัน (น้อยขึ้นก่อน)',
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_network_highlights_kind (kind, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ข้อความสั้นพร้อมไอคอนใต้แผนที่โลก';

INSERT INTO network_highlights (kind, sort_order, title_th, title_en, icon)
SELECT d.kind, d.sort_order, d.title_th, d.title_en, d.icon FROM (
    SELECT 'model' AS kind, 0 AS sort_order, 'นิเทศวิทยานิพนธ์ร่วมกับต่างประเทศ' AS title_th, 'International Co-supervision' AS title_en, 'pair-talk' AS icon UNION ALL
    SELECT 'model' AS kind, 1 AS sort_order, 'วิจัยร่วม' AS title_th, 'Collaborative Research' AS title_en, 'magnifier' AS icon UNION ALL
    SELECT 'model' AS kind, 2 AS sort_order, 'สนับสนุนการตีพิมพ์' AS title_th, 'Publication Support' AS title_en, 'document' AS icon UNION ALL
    SELECT 'model' AS kind, 3 AS sort_order, 'นักวิชาการรับเชิญและผู้เชี่ยวชาญบรรยาย' AS title_th, 'Visiting Scholar / Expert Talk' AS title_en, 'podium' AS icon UNION ALL
    SELECT 'model' AS kind, 4 AS sort_order, 'สัมมนาและเวทีวิจัยร่วม' AS title_th, 'Joint Symposium / Research Forum' AS title_en, 'group' AS icon UNION ALL
    SELECT 'model' AS kind, 5 AS sort_order, 'เส้นทางสู่การประชุมวิชาการนานาชาติ' AS title_th, 'International Conference Pathway' AS title_en, 'globe' AS icon UNION ALL
    SELECT 'value' AS kind, 6 AS sort_order, 'ยกระดับคุณภาพวิทยานิพนธ์' AS title_th, NULL AS title_en, 'mortarboard' AS icon UNION ALL
    SELECT 'value' AS kind, 7 AS sort_order, 'เพิ่มโอกาสการตีพิมพ์ระดับนานาชาติ' AS title_th, NULL AS title_en, 'globe-chart' AS icon UNION ALL
    SELECT 'value' AS kind, 8 AS sort_order, 'เปิดมุมมองวิจัยระดับสากล' AS title_th, NULL AS title_en, 'bulb' AS icon UNION ALL
    SELECT 'value' AS kind, 9 AS sort_order, 'สร้างเครือข่ายวิชาการหลังสำเร็จการศึกษา' AS title_th, NULL AS title_en, 'network' AS icon UNION ALL
    SELECT 'value' AS kind, 10 AS sort_order, 'สนับสนุน PLO2 และ PLO3' AS title_th, NULL AS title_en, 'target' AS icon
) AS d
WHERE NOT EXISTS (SELECT 1 FROM network_highlights);

-- ถ้าใช้ผู้ใช้ฐานข้อมูลตัวที่สองสำหรับหน้า admin (ดูท้ายไฟล์ 2026-10-27-04-admin.sql) ให้เพิ่มสิทธิ์ SELECT, INSERT, UPDATE, DELETE
