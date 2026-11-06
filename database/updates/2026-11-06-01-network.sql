-- KUEDU2IMPACT: ตารางสำหรับเมนู "เครือข่าย" (แผนที่โลกและแผนที่ประเทศไทย)
--   network_partners   = เครือข่ายวิจัยนานาชาติ (network-international.php)
--   community_projects = นวัตกรรมสู่ชุมชน (network-community.php)
-- phpMyAdmin → e2imedu → Import ใช้ได้ทั้ง MySQL 8 และ MariaDB 10.x
-- รันซ้ำได้ ไม่ลบหรือแก้ข้อมูลเดิม ตารางเริ่มต้นว่าง (หน้าเว็บแสดง "กำลังรวบรวมข้อมูลเครือข่าย" จนกว่าจะเพิ่มรายการ)
-- ต้องรัน 2026-10-27-01-structure.sql ก่อน เพราะรูปของโครงการอ้างตาราง media
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS network_partners (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  country_code     CHAR(2) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'รหัสประเทศ ISO 3166-1 alpha-2 ตัวพิมพ์ใหญ่ เช่น JP (ต้องมีใน config/countries.php)',
  country_name_th  VARCHAR(100) NOT NULL COMMENT 'ชื่อประเทศภาษาไทย (หน้า admin ใส่ให้เอง)',
  institution      VARCHAR(300) NOT NULL COMMENT 'ชื่อสถาบันหรือหน่วยงาน',
  institution_th   VARCHAR(300) NULL COMMENT 'ชื่อภาษาไทย (ถ้ามี)',
  description      TEXT NULL COMMENT 'รายละเอียดความร่วมมือ',
  url              VARCHAR(500) NULL COMMENT 'เว็บไซต์ https://…',
  related          TEXT NULL COMMENT 'JSON array ของโพสต์ที่เกี่ยวข้อง เช่น [{"kind":"research","id":"slug"}] (kind = research หรือ innovations)',
  is_public        TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = แสดง, 0 = ซ่อน',
  sort_order       INT NOT NULL DEFAULT 0 COMMENT 'ลำดับ (น้อยขึ้นก่อน)',
  KEY idx_network_country (country_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='เครือข่ายวิจัยนานาชาติ (หนึ่งแถวต่อหนึ่งสถาบัน หนึ่งประเทศมีได้หลายแถว)';

CREATE TABLE IF NOT EXISTS community_projects (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  province_code     VARCHAR(5) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'รหัสจังหวัด ISO 3166-2:TH เช่น TH-50 (ต้องมีใน config/thai-provinces.php)',
  province_name_th  VARCHAR(100) NOT NULL COMMENT 'ชื่อจังหวัดภาษาไทย (หน้า admin ใส่ให้เอง)',
  title             VARCHAR(400) NOT NULL COMMENT 'ชื่อโครงการ',
  community         VARCHAR(300) NULL COMMENT 'ชุมชนหรือหน่วยงานที่ร่วมงาน',
  summary           TEXT NULL COMMENT 'สรุปสั้นๆ',
  project_year      SMALLINT UNSIGNED NULL COMMENT 'ปี พ.ศ. เช่น 2568',
  image_id          INT UNSIGNED NULL COMMENT 'รูป: เลข id ในตาราง media',
  image             VARCHAR(255) NULL COMMENT 'รูปแบบเดิม (path) ใช้เมื่อ image_id ว่าง',
  related           TEXT NULL COMMENT 'JSON array ของโพสต์ที่เกี่ยวข้อง เช่น [{"kind":"innovations","id":"slug"}] (kind = innovations หรือ workshops)',
  is_public         TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = แสดง, 0 = ซ่อน',
  sort_order        INT NOT NULL DEFAULT 0 COMMENT 'ลำดับ (น้อยขึ้นก่อน)',
  KEY idx_community_province (province_code),
  CONSTRAINT fk_community_projects_image FOREIGN KEY (image_id) REFERENCES media (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='นวัตกรรมสู่ชุมชน (หนึ่งแถวต่อหนึ่งโครงการ หนึ่งจังหวัดมีได้หลายแถว)';

-- ถ้าใช้ผู้ใช้ฐานข้อมูลตัวที่สองสำหรับหน้า admin (ดูท้ายไฟล์ 2026-10-27-04-admin.sql) ให้เพิ่มสิทธิ์ SELECT, INSERT, UPDATE, DELETE
-- บนตาราง network_partners และ community_projects ให้ผู้ใช้นั้นด้วย ผู้ใช้ตัวที่อ่านอย่างเดียวได้ SELECT จาก e2imedu.* อยู่แล้ว

-- ตรวจผล: ควรเห็นตาราง network_partners และ community_projects
SHOW TABLES;
