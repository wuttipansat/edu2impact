-- Round 11 content update. Run in phpMyAdmin: click e2imedu > SQL tab > paste this file > Go.
-- Only UPDATE and INSERT statements; nothing is dropped, so edits you made in MySQL stay.
-- Safe to run more than once.
SET NAMES utf8mb4;

-- 3. Site name (shown in the title, footer, logo label and share cards)
UPDATE site_settings SET setting_value = 'KUEDU2IMPACT' WHERE setting_key = 'name';

-- 5. Public address of the site. Without it the site sends no og:image, og:url or canonical link,
--    and Facebook shows a link with no cover picture.
--    Set to the real address (https://, no slash at the end). The same value is in data/site.json.
UPDATE site_settings SET setting_value = 'https://edu2impact.edu.ku.ac.th' WHERE setting_key = 'site_url';

-- 6. Owner names: one style for every title. A space between abbreviated titles, none between "ดร." and the name
--    (รศ. ดร.ศศิเทพ). Two names had "ดร. " with a space. Each statement only touches rows that still have the old text.
UPDATE posts SET author = REPLACE(author, 'รศ. ดร. ศศิเทพ', 'รศ. ดร.ศศิเทพ') WHERE author LIKE '%รศ. ดร. ศศิเทพ%';
UPDATE posts SET author = REPLACE(author, 'รศ. ดร. ชาตรี', 'รศ. ดร.ชาตรี') WHERE author LIKE '%รศ. ดร. ชาตรี%';
-- the same name written inside the text of posts
UPDATE posts SET summary = REPLACE(summary, 'รศ. ดร. ศศิเทพ', 'รศ. ดร.ศศิเทพ') WHERE summary LIKE '%รศ. ดร. ศศิเทพ%';
UPDATE posts SET body = REPLACE(body, 'รศ. ดร. ศศิเทพ', 'รศ. ดร.ศศิเทพ') WHERE body LIKE '%รศ. ดร. ศศิเทพ%';
UPDATE posts SET summary = REPLACE(summary, 'รศ. ดร. ชาตรี', 'รศ. ดร.ชาตรี') WHERE summary LIKE '%รศ. ดร. ชาตรี%';
UPDATE posts SET body = REPLACE(body, 'รศ. ดร. ชาตรี', 'รศ. ดร.ชาตรี') WHERE body LIKE '%รศ. ดร. ชาตรี%';
-- Names the owner asked to correct (round 11 table was left empty): add one line per name here, for example
-- UPDATE posts SET author = 'ชื่อที่ถูกต้อง' WHERE author = 'ชื่อเดิมทั้งสตริง';

-- 7. Footer line: "© 2026 KUEDU2IMPACT, คณะศึกษาศาสตร์ มหาวิทยาลัยเกษตรศาสตร์"
UPDATE site_settings SET setting_value = 'คณะศึกษาศาสตร์ มหาวิทยาลัยเกษตรศาสตร์' WHERE setting_key = 'university';
