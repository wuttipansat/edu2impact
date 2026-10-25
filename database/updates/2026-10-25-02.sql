-- Round 12: Smart Garden author line lists every researcher named in the full research report (no "และคณะ").
-- Source: รายงานวิจัยฉบับสมบูรณ์ "Smart Garden with STEM Robotics and Coding", cover page. Re-runnable.
UPDATE posts SET author = 'รศ. ดร.ศศิเทพ ปิติพรเทพิน, ดร.สุกานดา จงเสริมตระกูล, รศ. ดร.ชาตรี ฝ่ายคำตา, ดร.ศรินย์พร ชัยวิศิษฎ์, สุขรัชต์ดา วงศ์บุญเอื้อ, ผศ. ดร.มนต์อมร ปรีชารัตน์ และ Prof. Dr. Samia Khan'
WHERE slug = 'smart-garden-stem-robotics-coding';

-- Coding4Change: all six members of the research team named on coding4change.online (project coordinator first).
UPDATE posts SET author = 'รศ. ดร.พงศ์ประพันธ์ พงษ์โสภณ, รศ. ดร.ชาตรี ฝ่ายคำตา, ผศ. ดร.ชนิศวรา เลิศอมรพงษ์, ดร.วีริศ กิตติวรากูล, ดร.วรัญญู ฉายาบรรณ์ และ ดร.สุธนิต เวชโช'
WHERE slug = 'coding4change-stem-sustainable-innovation';

-- References keep the short author form (first author + "และคณะ"); the full team is shown in its own section.
UPDATE posts SET refs = REPLACE(refs, 'ศศิเทพ ปิติพรเทพิน, สุกานดา จงเสริมตระกูล, ชาตรี ฝ่ายคำตา, ศรินย์พร ชัยวิศิษฎ์, สุขรัชต์ดา วงศ์บุญเอื้อ และมนต์อมร ปรีชารัตน์. (2569)', 'ศศิเทพ ปิติพรเทพิน และคณะ. (2569)') WHERE slug = 'smart-garden-stem-robotics-coding' AND refs LIKE '%สุขรัชต์ดา วงศ์บุญเอื้อ และมนต์อมร%';
