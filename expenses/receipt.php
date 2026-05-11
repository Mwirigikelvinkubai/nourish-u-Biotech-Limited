<?php
require_once __DIR__ . '/../config/config.php';
require_login();
$u = current_user();

$id = (int)get('id');
$stmt = $pdo->prepare("SELECT receipt_path, rep_id FROM expenses WHERE id=?");
$stmt->execute([$id]); $e = $stmt->fetch();
if (!$e || empty($e['receipt_path'])) { http_response_code(404); die('Not found'); }
if ($u['role']==='rep' && (int)$e['rep_id'] !== (int)$u['id']) { http_response_code(403); die('Not allowed'); }

$rel  = ltrim($e['receipt_path'], '/\\');
$path = ROOT_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
$real = realpath($path);
$root = realpath(UPLOAD_DIR);
if (!$real || !$root || !str_starts_with($real, $root)) { http_response_code(404); die('Not found'); }

header('Content-Type: ' . (mime_content_type($real) ?: 'application/octet-stream'));
header('Content-Length: ' . filesize($real));
header('Content-Disposition: inline; filename="' . basename($real) . '"');
readfile($real); exit;
