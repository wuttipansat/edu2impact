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
    'topic_fallback' => 'research.php',
];
