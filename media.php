<?php
/** Streams an uploaded image stored in the `media` table:  media.php?id=123 */
require_once __DIR__ . '/config/config.php';

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) { http_response_code(404); exit; }

$stmt = $conn->prepare('SELECT mime, data FROM media WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$stmt->bind_result($mime, $data);
if (!$stmt->fetch()) { http_response_code(404); exit; }
$stmt->close();

$etag = '"m' . $id . '-' . md5($data) . '"';
header('ETag: ' . $etag);
header('Cache-Control: public, max-age=31536000, immutable');
header('X-Content-Type-Options: nosniff');
if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) { http_response_code(304); exit; }
header('Content-Type: ' . $mime);
header('Content-Length: ' . strlen($data));
echo $data;
