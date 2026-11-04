<?php
// One picture sent by the post editor's drag-and-drop / paste (assets/js/editor.js). POST only, signed-in admins and editors,
// CSRF token required (admin_boot). The file goes through the same checks as the pictures page (type by content, 8 MB, resized
// to WebP, EXIF removed, share image). Answers JSON: {id, url, width, height} or {error} in Thai.
require_once dirname(__DIR__) . '/includes/admin/media-lib.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); header('Allow: POST'); echo json_encode(['error' => 'ต้องส่งแบบ POST']); exit; }
if (!$_POST && $_FILES) { $_POST['csrf'] = ''; }   // a picture sent with no token is a missing token (403), not a too-big upload
$user = admin_boot();   // not signed in: 303 to the login page; no/old CSRF token: 403
$reply = static function (array $data, int $code = 200): never { http_response_code($code); echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); exit; };
$file = $_FILES['file'] ?? null;
if (!is_array($file) || is_array($file['name'] ?? null)) { $reply(['error' => 'ยังไม่ได้เลือกไฟล์ (หรือไฟล์ใหญ่เกินที่เซิร์ฟเวอร์รับ)'], 400); }
$prep = admin_media_prepare($file);
if (is_string($prep)) { $reply(['error' => basename((string) ($file['name'] ?? '')) . ': ' . $prep], 422); }
if (strpos((string) $prep['mime'], 'image/') !== 0) { $reply(['error' => 'ส่งได้เฉพาะรูปภาพ'], 422); }
try { $id = admin_media_store($prep, basename((string) $file['name']), '', (int) $user['id']); }
catch (Throwable $e) { error_log('edu2impact admin upload.php: ' . $e->getMessage()); $reply(['error' => 'อัปโหลดไม่สำเร็จ: ฐานข้อมูลไม่รับไฟล์ (อาจใหญ่เกินไป)'], 500); }
$row = admin_db()->prepare('SELECT LEFT(sha256, 8) AS v, width, height FROM media WHERE id = ?');
$row->execute([$id]); $row = $row->fetch() ?: [];
$reply(['id' => $id, 'url' => 'media-img.php?id=' . $id . '&v=' . ($row['v'] ?? ''), 'width' => (int) ($row['width'] ?? 0), 'height' => (int) ($row['height'] ?? 0)]);
