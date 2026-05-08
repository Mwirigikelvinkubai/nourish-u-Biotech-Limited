<?php
/**
 * Authenticated download endpoint for files stored OUTSIDE the public root.
 * Currently serves client_documents only. Add more cases as needed.
 */
require_once __DIR__ . '/config/config.php';
require_login();
$u = current_user();

$docId = (int)get('doc', 0);
if (!$docId) { http_response_code(400); die('Missing doc'); }

$stmt = $pdo->prepare(
    "SELECT cd.*, c.rep_id
       FROM client_documents cd
       JOIN clients c ON c.id = cd.client_id
      WHERE cd.id = ?"
);
$stmt->execute([$docId]);
$d = $stmt->fetch();
if (!$d) { http_response_code(404); die('Not found'); }

if ($u['role'] === 'rep' && (int)$d['rep_id'] !== (int)$u['id']) {
    http_response_code(403); die('Not allowed');
}

$rel  = ltrim($d['file_path'], '/\\');           // e.g. uploads/clients/1/foo.pdf
$path = ROOT_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
$real = realpath($path);
$root = realpath(UPLOAD_DIR);

if (!$real || !$root || !str_starts_with($real, $root)) {
    http_response_code(404); die('File not found.');
}

$mime = mime_content_type($real) ?: 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($real));
header('Content-Disposition: inline; filename="' . basename($real) . '"');
readfile($real);
exit;
