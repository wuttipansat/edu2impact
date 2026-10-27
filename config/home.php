<?php
// Home page settings (index.php). Content itself always comes from data/*.json; this file only
// chooses and labels it. Records are referenced as ['kind' => research|innovations|workshops, 'id' => …];
// a reference to a missing or hidden ("public": false) record is skipped, so nothing here can break the page.
return [
    // Hero: one large + two smaller cards. Leave empty to use featured records first, then the newest.
    'hero' => [],

    // "เนื้อหาแนะนำ": a curated list. There are no view counts on this site, so this is an editors' pick,
    // never a popularity ranking. Leave empty to use featured records and records with an online tool.
    'recommended' => [
        ['kind' => 'innovations', 'id' => 'pck-canvas'],
        ['kind' => 'research', 'id' => 'coding4change-stem-sustainable-innovation'],
        ['kind' => 'innovations', 'id' => 'hemo-link'],
        ['kind' => 'innovations', 'id' => 'ku-care-u'],
        ['kind' => 'research', 'id' => 'nonthaburi-learning-city-wellbeing-curriculum'],
    ],

    // "สำรวจตามความสนใจ": each topic opens a listing page (`url`) or a site-search query (`query`).
    // A query topic shows how many records match; if a query finds nothing it falls back to `fallback`,
    // so a topic never leads to an empty result page. `icon` is one of the icons in home_topic_icon().
    'topics' => [
        ['label' => 'STEM Education', 'thai' => 'สะเต็มศึกษา', 'query' => 'สะเต็ม', 'icon' => 'atom'],
        ['label' => 'AI & Educational Technology', 'thai' => 'AI และเทคโนโลยีการศึกษา', 'query' => 'เทคโนโลยี', 'icon' => 'chip'],
        ['label' => 'Teacher Development', 'thai' => 'การพัฒนาครู', 'query' => 'ครู', 'icon' => 'teacher'],
        ['label' => 'Educational Research', 'thai' => 'งานวิจัยทางการศึกษา', 'url' => 'research.php', 'kind' => 'research', 'icon' => 'book'],
        ['label' => 'Learning Innovation', 'thai' => 'นวัตกรรมการเรียนรู้', 'url' => 'innovations.php', 'kind' => 'innovations', 'icon' => 'bulb'],
        // No post is about school leadership yet; the closest content is the learning-city facilitator work.
        ['label' => 'School Leadership', 'thai' => 'ผู้นำการเรียนรู้', 'query' => 'นักขับเคลื่อน', 'icon' => 'flag'],
    ],
    // When content comes from MySQL (private/database.php 'content_source' => 'mysql'), "ประชาสัมพันธ์" and "Scoop" are read
    // from the table home_items instead of the two lists below (templates/README-phpmyadmin.md); edit them there.
    // "ประชาสัมพันธ์": picture banners in a slider between the hero and "ผลงานใหม่ล่าสุด" (no page of its own, no menu item).
    // The text is part of the picture, so prepare each banner at 2:1 (1920 x 960 px, under 400 KB). Fields: title (read aloud as the
    // picture's description), image (assets/images/…), url (page or https address; "#" = not clickable), date (Y-m-d, optional,
    // shown under the banner). "label" is still accepted but not shown. Delete every row to hide the section.
    // The rows below are placeholders, marked "[ตัวอย่าง]".
    'promos' => [
        ['title' => '[ตัวอย่าง] เปิดรับสมัครเวิร์กช็อปออกแบบบทเรียนด้วย PCK Canvas สำหรับครูทุกกลุ่มสาระ', 'date' => '2026-10-22', 'label' => 'รับสมัคร', 'image' => 'assets/images/innovation-pck-canvas.webp', 'url' => '#'],
        ['title' => '[ตัวอย่าง] เชิญร่วมเสวนา "จากงานวิจัยสู่ห้องเรียน" ร่วมกับโรงเรียนเครือข่าย', 'date' => '2026-10-18', 'label' => 'กิจกรรม', 'image' => 'assets/images/nonthaburi-learning.webp', 'url' => '#'],
        ['title' => '[ตัวอย่าง] ผลงาน Coding4Change ได้รับเชิญนำเสนอในเวทีสะเต็มศึกษา', 'date' => '2026-10-12', 'label' => 'ข่าว', 'image' => 'assets/images/coding4change-pilot.webp', 'url' => '#'],
        ['title' => '[ตัวอย่าง] ประกาศรายชื่อโรงเรียนที่เข้าร่วมโครงการ Smart Garden รอบที่ 2', 'date' => '2026-10-05', 'label' => 'ประกาศ', 'image' => 'assets/images/smart-garden-cover.webp', 'url' => '#'],
        ['title' => '[ตัวอย่าง] ชุดปฏิบัติการ Hemo-Link เปิดให้ครูชีววิทยายืมไปทดลองใช้', 'date' => '2026-09-28', 'label' => 'ข่าว', 'image' => 'assets/images/hemo-link-cover.webp', 'url' => '#'],
        ['title' => '[ตัวอย่าง] ขอเชิญส่งผลงานเข้าร่วมเวทีนำเสนอนวัตกรรมการศึกษา ปี 2569', 'date' => '2026-09-20', 'label' => 'ประกาศ', 'image' => 'assets/images/clil-hand.webp', 'url' => '#'],
    ],

    // "Scoop": videos in a centred slider after "ผลงานใหม่ล่าสุด". Fields: title, youtube (full address of the video),
    // date (Y-m-d, optional), image (assets/images/… cover, optional; the YouTube thumbnail is used without it).
    // Accepted addresses: youtube.com/watch?v=…, youtu.be/…, youtube.com/shorts/… . Delete every row to hide the section.
    // The rows below are placeholders (public KU introduction videos), marked "[ตัวอย่าง]".
    'videos' => [
        ['title' => '[ตัวอย่าง] วีดิทัศน์แนะนำมหาวิทยาลัยเกษตรศาสตร์', 'youtube' => 'https://www.youtube.com/watch?v=eEspYsI-zz4'],
        ['title' => '[ตัวอย่าง] Introduction Kasetsart University (KU) Thai Version', 'youtube' => 'https://www.youtube.com/watch?v=itS7EHAzHjU', 'date' => '2025-05-20'],
        ['title' => '[ตัวอย่าง] Kasetsart University', 'youtube' => 'https://www.youtube.com/watch?v=GuEm7B_8KyA'],
        ['title' => '[ตัวอย่าง] VDO Introduction Kasetsart University, English subtitles', 'youtube' => 'https://www.youtube.com/watch?v=HsqyH5rjT6M', 'date' => '2025-12-08'],
    ],
    'topic_fallback' => 'research.php',
];
