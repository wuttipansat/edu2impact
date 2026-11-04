-- งานวิจัยใช้หมวดชุดเดียวกับนวัตกรรม (6 หมวดใน config/innovation-categories.php)
-- ล้างหมวดเก่าที่ไม่อยู่ในชุดนี้ เช่น "Learning" และ "Assessment" รันซ้ำได้ ไม่ลบโพสต์ ไม่แตะประเภทอื่น
-- phpMyAdmin → e2imedu → Import
UPDATE posts
   SET category = NULL
 WHERE kind = 'research'
   AND category IS NOT NULL
   AND category NOT IN ('สะเต็มศึกษา', 'เทคโนโลยีดิจิทัล', 'พัฒนาครู', 'ปฐมวัยบูรณาการ', 'สุขภาวะและพลศึกษา', 'ศึกษาอาชีวะ');
