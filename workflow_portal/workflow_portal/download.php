<?php
// Secure file download handler.
// Checks login and document ownership before serving any file.
require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$docId = (int) ($_GET['id'] ?? 0);
if ($docId < 1) { http_response_code(404); exit('File not found.'); }

$pdo  = db();
$stmt = $pdo->prepare('SELECT d.*, a.assigned_committee_id
    FROM documents d
    LEFT JOIN assignments a ON a.id = d.assignment_id
    WHERE d.id = ? AND d.is_active = 1 LIMIT 1');
$stmt->execute([$docId]);
$doc = $stmt->fetch();

if (!$doc) { http_response_code(404); exit('File not found.'); }

// Permission: user must be able to view the linked assignment (or be adminish)
if ($doc['assignment_id']) {
    if (!user_can_view_assignment($doc)) {
        http_response_code(403); exit('Access denied.');
    }
} elseif (!user_is_adminish()) {
    http_response_code(403); exit('Access denied.');
}

$filePath = UPLOAD_DIR . $doc['stored_file_name'];
if (!file_exists($filePath)) { http_response_code(404); exit('File not found on server.'); }

// Serve the file
$mime = $doc['mime_type'] ?: 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . rawurlencode($doc['original_file_name']) . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: private, no-cache');
readfile($filePath);
exit;