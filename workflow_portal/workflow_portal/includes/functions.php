<?php
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function base_url(string $path = ''): string
{
    $base = rtrim(APP_BASE_URL, '/');
    $path = ltrim($path, '/');
    return $base ? $base . '/' . $path : $path;
}

function redirect(string $path): never
{
    header('Location: ' . base_url($path));
    exit;
}

function flash(string $key, ?string $value = null): ?string
{
    if ($value !== null) {
        $_SESSION['_flash'][$key] = $value;
        return null;
    }

    $message = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return $message;
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

function verify_csrf(): void
{
    $token = $_POST['_csrf'] ?? '';
    if (!hash_equals($_SESSION['_csrf_token'] ?? '', $token)) {
        http_response_code(422);
        exit('Invalid CSRF token.');
    }
}

function status_badge_class(string $status): string
{
    return match ($status) {
        'approved' => 'status-approved',
        'assigned' => 'status-assigned',
        'in_progress' => 'status-progress',
        'waiting_blocked' => 'status-blocked',
        'ready_for_review' => 'status-review',
        'completed' => 'status-completed',
        'archived' => 'status-archived',
        'returned_for_revision' => 'status-returned',
        'pending_handoff', 'in_receiving_review', 'resubmitted' => 'status-handoff',
        default => 'status-approved',
    };
}

function status_label(string $status): string
{
    return match ($status) {
        'approved' => 'Approved',
        'assigned' => 'Assigned',
        'in_progress' => 'In Progress',
        'waiting_blocked' => 'Waiting / Blocked',
        'ready_for_review' => 'Ready for Review',
        'completed' => 'Completed',
        'archived' => 'Archived',
        'pending_handoff' => 'Pending Handoff',
        'in_receiving_review' => 'In Receiving Review',
        'returned_for_revision' => 'Returned for Revision',
        'resubmitted' => 'Resubmitted',
        default => ucwords(str_replace('_', ' ', $status)),
    };
}

function work_type_label(string $type): string
{
    return match ($type) {
        'official' => 'Official',
        'handoff' => 'Handoff',
        'revision' => 'Revision',
        default => ucfirst($type),
    };
}

function committee_badge_class(?string $shortCode): string
{
    return strtolower((string) $shortCode);
}

function format_date(?string $date): string
{
    if (!$date) {
        return '—';
    }
    try {
        return (new DateTime($date))->format('M j, Y');
    } catch (Throwable) {
        return $date;
    }
}

function priority_label(?string $priority): string
{
    return ucfirst((string) $priority);
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function handle_document_upload(int $assignmentId, ?int $subtaskId, int $userId): ?string
{
    if (empty($_FILES['doc_file']['tmp_name'])) return 'No file selected.';

    $file      = $_FILES['doc_file'];
    $origName  = basename($file['name']);
    $ext       = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    $allowed   = explode(',', ALLOWED_EXTENSIONS);
    $maxBytes  = UPLOAD_MAX_MB * 1024 * 1024;

    if (!in_array($ext, $allowed, true))    return 'File type .' . $ext . ' is not allowed.';
    if ($file['size'] > $maxBytes)          return 'File exceeds the ' . UPLOAD_MAX_MB . ' MB limit.';
    if ($file['error'] !== UPLOAD_ERR_OK)   return 'Upload error (code ' . $file['error'] . ').';

    $storedName = date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $destPath   = UPLOAD_DIR . $storedName;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) return 'Could not save the file. Check server permissions.';

    $title       = trim($_POST['doc_title'] ?? '') ?: $origName;
    $category    = trim($_POST['doc_category'] ?? 'Other');
    $description = trim($_POST['doc_description'] ?? '');
    $isFinal     = isset($_POST['doc_is_final']) ? 1 : 0;
    $mime        = $file['type'] ?: mime_content_type($destPath);

    db()->prepare('INSERT INTO documents
            (assignment_id, subtask_id, title, original_file_name, stored_file_name,
             file_path, file_extension, mime_type, file_size, category,
             description, is_final_version, uploaded_by_user_id, uploaded_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())')
        ->execute([
            $assignmentId,
            $subtaskId ?: null,
            $title, $origName, $storedName,
            'uploads/' . $storedName,
            $ext, $mime, $file['size'],
            $category, $description ?: null,
            $isFinal, $userId,
        ]);

    return null; // null = success
}