<?php
// Pages in the site search (search.php). `hero` names a key in config/heroes.php whose
// eyebrow, title and intro are added to the page's searchable text. Titles match the menu in config/navigation.php.
return [
    ['title' => 'หน้าแรก', 'url' => 'index.php', 'hero' => 'home',
     'summary' => 'งานวิจัย นวัตกรรม เครื่องมือออนไลน์ เวิร์กช็อป และข่าวล่าสุด',
     'keywords' => 'หน้าแรก home edu2impact research to impact ล่าสุด'],
    ['title' => 'งานวิจัยและบทความ', 'url' => 'research.php', 'hero' => 'research',
     'summary' => 'สรุปงานวิจัยทางการศึกษา ว่าศึกษาอะไร พบอะไร และนำไปใช้อย่างไร',
     'keywords' => 'คลังความรู้ งานวิจัย บทความ ผลงานวิจัย research library evidence หลักฐาน'],
    ['title' => 'สื่อและคู่มือ', 'url' => 'media.php', 'hero' => 'media',
     'summary' => 'คู่มือและสื่อการสอนที่ดาวน์โหลดได้',
     'keywords' => 'คลังความรู้ สื่อ คู่มือ media guide ดาวน์โหลด'],
    ['title' => 'นวัตกรรมการศึกษา', 'url' => 'innovations.php', 'hero' => 'innovations',
     'summary' => 'ชุดกิจกรรม แบบจำลอง และหลักสูตรที่ครูนำไปใช้กับผู้เรียนได้',
     'keywords' => 'นวัตกรรม innovation innovations สื่อการสอน ห้องเรียน'],
    ['title' => 'อบรมและเวิร์กช็อป', 'url' => 'workshops.php', 'hero' => 'workshops',
     'summary' => 'เวิร์กช็อปแบบลงมือทำสำหรับครู ติดต่อทีมให้ไปจัดที่โรงเรียนได้',
     'keywords' => 'บริการของเรา บริการ กิจกรรม เวิร์กช็อป workshop workshops อบรม ขอจัดเวิร์กช็อป'],
    ['title' => 'ร้านค้า', 'url' => 'shop.php', 'hero' => 'shop',
     'summary' => 'ชุดสื่อและอุปกรณ์สำหรับครูและโรงเรียน พร้อมลิงก์สั่งซื้อ',
     'keywords' => 'ร้านค้า ซื้อ สั่งซื้อ สินค้า ราคา shop store ชุดสื่อ อุปกรณ์'],
    ['title' => 'เกี่ยวกับเรา', 'url' => 'about.php', 'hero' => 'about',
     'summary' => 'เว็บไซต์รวมงานวิจัยและนวัตกรรมด้านการศึกษาของมหาวิทยาลัยเกษตรศาสตร์',
     'keywords' => 'เกี่ยวกับเรา about edu2impact แพลตฟอร์ม มหาวิทยาลัยเกษตรศาสตร์ คณะศึกษาศาสตร์'],
    ['title' => 'ติดต่อเรา', 'url' => 'contact.php', 'hero' => '',
     'summary' => 'โทรศัพท์ อีเมล และที่อยู่สำหรับติดต่อทีม EDU2IMPACT',
     'keywords' => 'ติดต่อ contact อีเมล โทรศัพท์ ขอใช้ ขอจัดเวิร์กช็อป สอบถาม'],
];
