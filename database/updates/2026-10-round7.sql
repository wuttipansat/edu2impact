-- Round 7 content update. Run in phpMyAdmin: click e2imedu > SQL tab > paste this file > Go.
-- Only UPDATE and INSERT statements; nothing is dropped, so edits you made in MySQL stay.
-- Safe to run more than once.
SET NAMES utf8mb4;

-- 4. Innovation categories (new names, see config/innovation-categories.php)
UPDATE posts SET category = 'เทคโนโลยีดิจิทัล' WHERE kind = 'innovations' AND slug = 'ku-care-u';
UPDATE posts SET category = 'สะเต็มศึกษา' WHERE kind = 'innovations' AND slug = 'smart-garden-stem-robotics-coding';
UPDATE posts SET category = 'พัฒนาครู' WHERE kind = 'innovations' AND slug = 'pck-canvas';
UPDATE posts SET category = 'สะเต็มศึกษา' WHERE kind = 'innovations' AND slug = 'hemo-link';

-- 7. Contact details (site_settings rows already exist, so UPDATE is enough)
UPDATE site_settings SET setting_value = 'research.eduku@gmail.com' WHERE setting_key = 'email';
UPDATE site_settings SET setting_value = '02-942-8268 ต่อ 731000' WHERE setting_key = 'phone';
UPDATE site_settings SET setting_value = 'หน่วยบริการทั่วไป 1-101 อาคาร 1 ชั้น 1 สำนักงานเลขานุการ คณะศึกษาศาสตร์ มหาวิทยาลัยเกษตรศาสตร์' WHERE setting_key = 'address';
