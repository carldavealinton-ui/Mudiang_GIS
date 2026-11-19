<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/evidence_support.php';
require_once __DIR__.'/access_control.php';
if (!isset($_SESSION['user_id'])) { http_response_code(403); exit('Please sign in to view evidence.'); }
$query = $pdo->prepare('SELECT e.*, c.user_id,c.assigned_staff_id FROM complaint_evidence e JOIN complaints c ON c.id = e.complaint_id WHERE e.id = ?');
$query->execute([(int) ($_GET['id'] ?? 0)]);
$file = $query->fetch(PDO::FETCH_ASSOC);
if (!$file || !canAccessComplaint($file)) {
    http_response_code(404); exit('Evidence not found.');
}
$path = realpath(__DIR__ . '/storage/' . $file['path']);
$root = realpath(EVIDENCE_DIRECTORY);
if (!$path || !$root || !str_starts_with(str_replace('\\', '/', $path), str_replace('\\', '/', $root) . '/') || !is_file($path)) {
    http_response_code(404); exit('Evidence file is unavailable.');
}
if (!in_array($file['mime_type'], ['image/jpeg', 'image/png', 'image/webp', 'video/mp4', 'video/webm', 'video/quicktime'], true)) {
    http_response_code(404); exit('Unsupported evidence file.');
}
session_write_close();
$size = filesize($path);
$start = 0;
$end = $size - 1;
header('Content-Type: ' . $file['mime_type']);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
header('Accept-Ranges: bytes');
$disposition = isset($_GET['download']) ? 'attachment' : 'inline';
header('Content-Disposition: ' . $disposition . '; filename="evidence-' . (int) $file['id'] . '"; filename*=UTF-8\'\'' . rawurlencode($file['original_name']));
// Support browser video seeking without reading the entire video into memory.
if (isset($_SERVER['HTTP_RANGE'])) {
    if (!preg_match('/^bytes=(\d*)-(\d*)$/', trim($_SERVER['HTTP_RANGE']), $match) || ($match[1] === '' && $match[2] === '')) {
        http_response_code(416); header('Content-Range: bytes */' . $size); exit;
    }
    if ($match[1] === '') {
        $suffix = (int) $match[2];
        if ($suffix <= 0) { http_response_code(416); header('Content-Range: bytes */' . $size); exit; }
        $start = max(0, $size - $suffix);
    } else {
        $start = (int) $match[1];
        if ($match[2] !== '') $end = min($end, (int) $match[2]);
    }
    if ($start > $end || $start >= $size) { http_response_code(416); header('Content-Range: bytes */' . $size); exit; }
    http_response_code(206);
    header("Content-Range: bytes $start-$end/$size");
}
header('Content-Length: ' . ($end - $start + 1));
if ($_SERVER['REQUEST_METHOD'] === 'HEAD') exit;
$handle = fopen($path, 'rb');
fseek($handle, $start);
$remaining = $end - $start + 1;
while ($remaining > 0 && !feof($handle) && !connection_aborted()) {
    $chunk = fread($handle, min(65536, $remaining));
    if ($chunk === false || $chunk === '') break;
    echo $chunk;
    $remaining -= strlen($chunk);
}
fclose($handle);
