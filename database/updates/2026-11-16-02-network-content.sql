-- KUEDU2IMPACT: ใส่แท็ก สรุปหนึ่งประโยค และผู้ประสานงาน ให้ 20 สถาบันในเครือข่ายวิจัยนานาชาติ
-- phpMyAdmin → e2imedu → Import ใช้ได้ทั้ง MySQL 8 และ MariaDB 10.x
-- รันหลัง 2026-11-16-01-network-tags.sql
-- ไฟล์นี้ใช้ UPDATE อย่างเดียว ไม่ลบแถวใด จับคู่ด้วยชื่อสถาบัน (institution) ตามที่แสดงบนเว็บวันที่ 16 พ.ย. 2569
-- ถ้าชื่อในฐานข้อมูลสะกดต่างไปแม้แต่ตัวเดียว แถวนั้นจะไม่ถูกแก้ ดูจากตารางตรวจผลท้ายไฟล์ (แถวที่ summary และ tags ยังว่าง)
-- รันซ้ำได้: เขียนทับค่าเดิมของสามคอลัมน์นี้ด้วยค่าเดียวกัน ไม่แตะ description, url, related, is_public, sort_order
-- 6 แถวท้าย (MII-STEM ยกเว้น) ยังไม่มีข้อมูลเฉพาะพอจะเขียน summary จึงเว้นว่าง Ibaraki ยังไม่ใส่แท็ก ให้เลือกเองใน admin/network.php
SET NAMES utf8mb4;

UPDATE network_partners SET tags = 'co-supervision,research', summary = 'ร่วมนิเทศวิทยานิพนธ์ วิจัยร่วม และช่วยนิสิตตีพิมพ์ผลงาน', people = 'Prof. Dr. Anthony Clarke, Prof. Dr. Samia Khan' WHERE institution = 'University of British Columbia';
UPDATE network_partners SET tags = 'co-supervision,research', summary = 'ร่วมนิเทศวิทยานิพนธ์และให้คำปรึกษาด้านการวิจัย', people = 'Prof. Dr. Noriyuki Inoue' WHERE institution = 'Waseda University';
UPDATE network_partners SET tags = 'co-supervision', summary = 'ร่วมนิเทศวิทยานิพนธ์และดูแลคุณภาพงานวิจัยของนิสิต', people = 'Prof. Dr. P John Williams' WHERE institution = 'Curtin University';
UPDATE network_partners SET tags = 'exchange', summary = 'แลกเปลี่ยนบัณฑิตศึกษาผ่าน TWINCLE, Sakura และ Physics Program และส่งนักศึกษามาที่คณะทุกเดือนกันยายน', people = NULL WHERE institution = 'Chiba University';
UPDATE network_partners SET tags = 'exchange', summary = 'แลกเปลี่ยนหนึ่งภาคการศึกษาในโครงการ Campus Asia ร่วมกับ Changchun University และ Beijing Normal University', people = NULL WHERE institution = 'Hiroshima University';
UPDATE network_partners SET tags = 'exchange', summary = 'แลกเปลี่ยนนิสิตสองทาง รับนักศึกษาหนึ่งภาคการศึกษาผ่าน KUOSSP', people = NULL WHERE institution = 'Kyoai Gakuen University';
UPDATE network_partners SET tags = 'exchange,research', summary = 'แลกเปลี่ยนบัณฑิตศึกษาและวิจัยเปรียบเทียบคณิตศาสตร์ปฐมวัย ไทย เกาหลีใต้ และสหรัฐฯ', people = NULL WHERE institution = 'Konkuk University';
UPDATE network_partners SET tags = 'exchange,visiting-scholar,conference', summary = 'แลกเปลี่ยนนิสิตปริญญาเอก (iIREP 2024) และอาจารย์ของคณะเป็น Keynote Speaker ที่ NDHU', people = 'Prof. Te-Sheng Chang' WHERE institution = 'National Dong Hwa University (NDHU)';
UPDATE network_partners SET tags = 'practicum,exchange', summary = 'ฝึกสอนข้ามประเทศใน EDU KU–PNU–UIC Teaching Practicum Exchange และโครงการ AsTEN STEP', people = NULL WHERE institution = 'Philippine Normal University (PNU)';
UPDATE network_partners SET tags = 'exchange', summary = 'นักศึกษา Fudan มาแลกเปลี่ยนวิชาการและวัฒนธรรมกับโรงเรียนสาธิต มก. เมื่อเมษายน 2569', people = NULL WHERE institution = 'Fudan University';
UPDATE network_partners SET tags = 'exchange', summary = 'สถาบันในเครือข่าย AsTEN จัดกิจกรรมแลกเปลี่ยนนักศึกษาร่วมกันเมื่อธันวาคม 2568', people = NULL WHERE institution = 'University of Social Sciences and Humanities, Vietnam National University, Hanoi (USSH)';
UPDATE network_partners SET tags = 'visiting-scholar,conference', summary = 'นักวิชาการรับเชิญ 17–28 พ.ย. 2569 และ Keynote ในงาน CU-KU Joint International Conference in Education 2026', people = 'Prof. Mary Earick' WHERE institution = 'Northern Illinois University (NIU)';
UPDATE network_partners SET tags = 'visiting-scholar,practicum', summary = 'นักวิชาการรับเชิญ และนักศึกษามาฝึกประสบการณ์วิชาชีพที่โรงเรียนสาธิต มก.', people = 'Prof. Scott Ashmann' WHERE institution = 'University of Wisconsin–Green Bay (UWGB)';
UPDATE network_partners SET tags = 'exchange,visiting-scholar', summary = 'แลกเปลี่ยนนิสิตหนึ่งภาคการศึกษา หลักสูตรออนไลน์ระดับปริญญาตรี และวิทยากรด้านการวิเคราะห์ข้อมูลเชิงคุณภาพ', people = 'Dr. Tang Wee' WHERE institution = 'National Institute of Education (NIE), Nanyang Technological University';
UPDATE network_partners SET tags = 'research', summary = 'วิจัยสะเต็มศึกษาร่วมในเครือข่าย MII-STEM', people = NULL WHERE institution = 'MII-STEM Network, National Institute of Education, NTU';
UPDATE network_partners SET tags = 'conference', summary = NULL, people = NULL WHERE institution = 'Seoul National University';
UPDATE network_partners SET tags = 'conference', summary = NULL, people = NULL WHERE institution = 'National Taiwan Normal University';
UPDATE network_partners SET tags = 'research', summary = NULL, people = NULL WHERE institution = 'University of Groningen';
UPDATE network_partners SET tags = 'visiting-scholar', summary = NULL, people = NULL WHERE institution = 'The University of Hong Kong';
UPDATE network_partners SET tags = NULL, summary = NULL, people = NULL WHERE institution = 'Ibaraki University';

-- ตรวจผล: ควรเห็น 20 แถว แถวไหนที่ tags ว่าง (ยกเว้น Ibaraki) แปลว่าชื่อไม่ตรง ให้แก้ผ่าน admin/network.php แทน
SELECT id, institution, tags, summary, people FROM network_partners ORDER BY sort_order, id;
