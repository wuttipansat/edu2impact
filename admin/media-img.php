<?php
// Serves a stored file to a signed-in admin only, including files that are not used anywhere yet (public file.php hides those).
require_once dirname(__DIR__) . '/includes/admin/core.php';
admin_headers();
auth_private_transport();
try { auth_start(); $user = auth_user(); } catch (PDOException $e) { http_response_code(503); exit; }
if (!$user || !in_array($user['role'], ADMIN_ROLES, true)) { http_response_code(403); exit; }
session_write_close();
$id = get_int('id');
if (!$id) { http_response_code(404); exit; }
$st = admin_db()->prepare('SELECT mime, sha256, data, filename FROM media WHERE id = ?');
$st->execute([$id]);
$row = $st->fetch();
if (!$row) { http_response_code(404); exit; }
$mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($row['data']);
if (!in_array($mime, ['image/webp', 'image/jpeg', 'image/png', 'application/pdf'], true)) { http_response_code(404); exit; }
header('Content-Type: ' . $mime);
header('Cache-Control: private, max-age=300');
header('ETag: "' . $row['sha256'] . '"');
header('X-Content-Type-Options: nosniff');
if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === '"' . $row['sha256'] . '"') { http_response_code(304); exit; }
if ($mime === 'application/pdf') { header('Content-Disposition: inline; filename="file.pdf"'); }
else { header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; sandbox"); }
header('Content-Length: ' . strlen($row['data']));
echo $row['data'];
