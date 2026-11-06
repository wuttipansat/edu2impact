# แผนที่ของหน้าเครือข่าย

| ไฟล์ | ใช้ที่ | เนื้อหา |
| --- | --- | --- |
| world.svg | network-international.php | แผนที่โลก projection แบบ Equal Earth ไม่รวมทวีปแอนตาร์กติกา มีขอบโลกและเส้นกริดทุก 10° อยู่ใต้ประเทศ แต่ละประเทศเป็น `<path>` ที่มี `data-code` (ISO 3166-1 alpha-2), `data-name`, `data-region`, `data-cx`, `data-cy` (จุดกลางของส่วนที่ใหญ่ที่สุด ใช้เป็นปลายเส้นเครือข่าย) ประเทศเล็กมี `data-small` ไว้วางวงกลมให้กดได้ |
| thailand.svg | network-community.php | แผนที่ประเทศไทย projection แบบ Mercator ครบ 77 จังหวัด แต่ละจังหวัดเป็น `<path>` ที่มี `data-code` (ISO 3166-2:TH เช่น TH-10), `data-name`, `data-cx`, `data-cy` มีประเทศเพื่อนบ้าน (ตัดเฉพาะรอบประเทศไทย) และชื่อประเทศ/ทะเลจางๆ เป็นบริบท |

ทั้งสองไฟล์มี `data-origin` คือตำแหน่ง ม.เกษตรศาสตร์ บางเขน ในหน่วยของแผนที่

ไฟล์ SVG ไม่มี `style` หรือ `<script>` เพราะ CSP ของเว็บไม่อนุญาต สีทั้งหมดอยู่ใน assets/css/network.css และเว็บฝัง SVG นี้ลงในหน้าโดยตรง (includes/network-map.php)

## แหล่งที่มาและลิขสิทธิ์

ข้อมูลขอบเขตมาจาก Natural Earth (https://www.naturalearthdata.com) ซึ่งเป็น public domain ใช้ได้ทุกวัตถุประสงค์โดยไม่ต้องอ้างอิง
- world.svg: ne_50m_admin_0_countries
- ประเทศเพื่อนบ้านใน thailand.svg: ne_50m_admin_0_countries
- thailand.svg: ne_10m_admin_1_states_provinces (เฉพาะ iso_a2 = TH, 77 จังหวัด)

ขอบเขตเป็นข้อมูลอ้างอิงเชิงภาพ ลดรายละเอียดลงแล้ว ไม่ใช่ข้อมูลทางการสำหรับระบุเส้นเขตแดน ชื่อภาษาไทยของประเทศมาจาก CLDR (config/countries.php) และชื่อจังหวัดอยู่ใน config/thai-provinces.php

## สร้างใหม่

ทำเมื่อต้องการปรับความละเอียดหรือ projection เท่านั้น ไฟล์ SVG ถูก commit ไว้แล้ว เพราะโฮสต์ไม่มี build step ดูขั้นตอนในหัวไฟล์ tools/build_maps.mjs
