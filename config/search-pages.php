<?php
// Pages in the site search (search.php). `hero` names a key in config/heroes.php whose
// eyebrow, title and intro are added to the page's searchable text. Partner groups are
// added automatically from config/partner-groups.php.
return [
    ['title' => 'Home', 'url' => 'index.php', 'hero' => 'home',
     'summary' => 'หน้าแรกของ EDU2IMPACT: งานวิจัย นวัตกรรม เวิร์กช็อป และกิจกรรมล่าสุด',
     'keywords' => 'หน้าแรก home edu2impact research to impact ล่าสุด กิจกรรม สัมมนา'],
    ['title' => 'Research', 'url' => 'research.php', 'hero' => 'research',
     'summary' => 'คลังผลงานวิจัยทางการศึกษาที่นำไปใช้ได้จริง',
     'keywords' => 'งานวิจัย ผลงานวิจัย research library evidence หลักฐาน'],
    ['title' => 'Innovation', 'url' => 'innovations.php', 'hero' => 'innovations',
     'summary' => 'นวัตกรรมการศึกษาที่พร้อมทดลองใช้ในห้องเรียน',
     'keywords' => 'นวัตกรรม innovation innovations เครื่องมือ สื่อการสอน'],
    ['title' => 'Workshop', 'url' => 'workshops.php', 'hero' => 'workshops',
     'summary' => 'ตารางเวิร์กช็อปสำหรับครูและเครือข่ายการศึกษา',
     'keywords' => 'เวิร์กช็อป workshop workshops อบรม โพสต์เวิร์กช็อป ขอจัดเวิร์กช็อป'],
    ['title' => 'Partners', 'url' => 'partners.php', 'hero' => 'partners',
     'summary' => 'เครือข่ายของ EDU2IMPACT ครอบคลุมผู้สร้างความรู้ ผู้ใช้ความรู้ และผู้สนับสนุนการขยายผล',
     'keywords' => 'พันธมิตร ผู้มีส่วนร่วม เครือข่าย partners partner network ความร่วมมือ'],
    ['title' => 'About', 'url' => 'about.php', 'hero' => 'about',
     'summary' => 'EDU2IMPACT คือพื้นที่กลางสำหรับค้นพบผลงานวิจัย เข้าใจหลักฐาน และเชื่อมต่อกับนักวิจัยหรือพันธมิตร',
     'keywords' => 'เกี่ยวกับเรา about edu2impact แพลตฟอร์ม มหาวิทยาลัยเกษตรศาสตร์ คณะศึกษาศาสตร์'],
    ['title' => 'ติดต่อเรา', 'url' => 'contact.php', 'hero' => '',
     'summary' => 'เริ่มต้นความร่วมมือใหม่ สำหรับสถานศึกษา นักวิจัย และหน่วยงานที่สนใจ',
     'keywords' => 'ติดต่อ contact connect with us อีเมล โทรศัพท์ ความร่วมมือ collaboration'],
];
