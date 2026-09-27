<?php
require __DIR__ . '/auth.php';

$file = basename($_GET['f'] ?? '');
if ($file === '') {
    http_response_code(400);
    exit('missing file');
}

$path = __DIR__ . '/data/attachments/' . $file;
if (!file_exists($path)) {
    http_response_code(404);
    exit('not found');
}

$name = $_GET['name'] ?? $file;
$mime = $_GET['mime'] ?? 'application/octet-stream';

header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . rawurlencode($name) . '"');
header('Content-Length: ' . filesize($path));
readfile($path);
exit;