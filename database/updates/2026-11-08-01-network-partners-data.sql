-- KUEDU2IMPACT: ข้อมูลเครือข่ายวิจัยนานาชาติ (network-international.php) 8 สถาบัน
-- phpMyAdmin → e2imedu → Import ต้องรัน 2026-11-06-01-network.sql ก่อน (สร้างตาราง network_partners)
-- description: หนึ่งบรรทัด = หนึ่ง bullet แสดงทั้งตอน hover บนแผนที่และในรายการใต้แผนที่
-- รันซ้ำได้: เพิ่มเฉพาะสถาบันที่ยังไม่มี แล้วอัปเดต description และ url ของทั้ง 8 สถาบันให้ตรงกับไฟล์นี้
SET NAMES utf8mb4;

-- 1) เพิ่มสถาบันที่ยังไม่มีในตาราง
INSERT INTO network_partners (country_code, country_name_th, institution, institution_th, description, url, related, is_public, sort_order)
SELECT d.country_code, d.country_name_th, d.institution, NULL, NULL, NULL, NULL, 1, d.sort_order FROM (
  SELECT 'CA' AS country_code, 'แคนาดา' AS country_name_th, 'University of British Columbia' AS institution, 10 AS sort_order
  UNION ALL SELECT 'NL', 'เนเธอร์แลนด์', 'University of Groningen', 20
  UNION ALL SELECT 'JP', 'ญี่ปุ่น', 'Waseda University', 30
  UNION ALL SELECT 'HK', 'ฮ่องกง', 'The University of Hong Kong', 40
  UNION ALL SELECT 'TW', 'ไต้หวัน', 'National Taiwan Normal University', 50
  UNION ALL SELECT 'KR', 'เกาหลีใต้', 'Seoul National University', 60
  UNION ALL SELECT 'SG', 'สิงคโปร์', 'MII-STEM Network, National Institute of Education, NTU', 70
  UNION ALL SELECT 'AU', 'ออสเตรเลีย', 'Curtin University', 80
) AS d
WHERE NOT EXISTS (SELECT 1 FROM network_partners p WHERE p.institution = d.institution COLLATE utf8mb4_unicode_ci);

-- 2) bullets และเว็บไซต์ของแต่ละสถาบัน
UPDATE network_partners SET url = 'https://www.ubc.ca', description =
'นิเทศวิทยานิพนธ์ร่วมกับต่างประเทศ
วิจัยร่วมและสนับสนุนการตีพิมพ์
Prof. Dr. Anthony Clarke
Prof. Dr. Samia Khan'
WHERE institution = 'University of British Columbia';

UPDATE network_partners SET url = 'https://www.rug.nl', description =
'วิจัยร่วม
เครือข่ายวิชาการนานาชาติ'
WHERE institution = 'University of Groningen';

UPDATE network_partners SET url = 'https://www.waseda.jp', description =
'นิเทศวิทยานิพนธ์ร่วมกับต่างประเทศ
แลกเปลี่ยนทางวิชาการและให้คำปรึกษาด้านการวิจัย
Prof. Dr. Noriyuki Inoue'
WHERE institution = 'Waseda University';

UPDATE network_partners SET url = 'https://www.hku.hk', description =
'นักวิชาการรับเชิญและผู้เชี่ยวชาญบรรยาย
แลกเปลี่ยนทางวิชาการ'
WHERE institution = 'The University of Hong Kong';

UPDATE network_partners SET url = 'https://www.ntnu.edu.tw', description =
'สัมมนาและเวทีวิจัยร่วม
เครือข่ายวิชาการ'
WHERE institution = 'National Taiwan Normal University';

UPDATE network_partners SET url = 'https://www.snu.ac.kr', description =
'เครือข่ายสัมมนาร่วม
เวทีนำเสนอผลงานวิจัย'
WHERE institution = 'Seoul National University';

UPDATE network_partners SET url = 'https://www.nie.edu.sg', description =
'เครือข่ายวิจัยสะเต็มศึกษา
วิจัยร่วม
แลกเปลี่ยนทางวิชาการระดับวิชาชีพ'
WHERE institution = 'MII-STEM Network, National Institute of Education, NTU';

UPDATE network_partners SET url = 'https://www.curtin.edu.au', description =
'นิเทศวิทยานิพนธ์ร่วมกับต่างประเทศ
สนับสนุนคุณภาพวิทยานิพนธ์
Prof. Dr. P John Williams'
WHERE institution = 'Curtin University';
