<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

date_default_timezone_set(TIMEZONE);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name(SESSION_NAME);
    session_start();
}

function has_any_users(): bool
{
    try {
        return (int) db()->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0;
    } catch (Throwable) {
        return false;
    }
}

function current_user(): ?array
{
    static $user = false;

    if ($user !== false) {
        return $user;
    }

    $id = $_SESSION['user_id'] ?? null;
    if (!$id) {
        $user = null;
        return null;
    }

    $stmt = db()->prepare('SELECT * FROM users WHERE id = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([$id]);
    $user = $stmt->fetch() ?: null;

    return $user;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function require_login(): void
{
    if (!current_user()) {
        flash('error', 'Please log in first.');
        redirect('login.php');
    }
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function user_role(): ?string
{
    return current_user()['role_level'] ?? null;
}

function user_is_adminish(): bool
{
    return in_array(user_role(), ['superadmin', 'sc_admin'], true);
}

function user_is_sc_member(): bool
{
    // SC Admin and SuperAdmin always count
    if (user_is_adminish()) return true;
    $user = current_user();
    if (!$user) return false;
    // sc_member and all committee admin roles implicitly have SC Member access
    if (in_array($user['role_level'], ['sc_member', 'cc_admin', 'fc_admin', 'ardc_admin'], true)) return true;
    // Check if the user belongs to the Steering Committee
    $stmt = db()->prepare('SELECT 1 FROM user_committee_memberships m
        JOIN committees c ON c.id = m.committee_id
        WHERE m.user_id = ? AND c.short_code = "SC" LIMIT 1');
    $stmt->execute([(int) $user['id']]);
    return (bool) $stmt->fetchColumn();
}

function user_committee_ids(int $userId): array
{
    $stmt = db()->prepare('SELECT committee_id FROM user_committee_memberships WHERE user_id = ?');
    $stmt->execute([$userId]);
    return array_map('intval', array_column($stmt->fetchAll(), 'committee_id'));
}

function user_can_view_assignment(array $assignment): bool
{
    $user = current_user();
    if (!$user) return false;

    // SC members (including SC Admin / SuperAdmin) see everything
    if (user_is_sc_member()) return true;

    // Everyone else: only if they are lead or a support person on this assignment
    $uid = (int) $user['id'];
    if ((int) $assignment['lead_user_id'] === $uid) return true;

    $stmt = db()->prepare('SELECT 1 FROM assignment_supporting_members
        WHERE assignment_id = ? AND user_id = ? LIMIT 1');
    $stmt->execute([(int) $assignment['id'], $uid]);
    return (bool) $stmt->fetchColumn();
}

function user_can_create_assignment(): bool
{
    return in_array(user_role(), ['superadmin', 'sc_admin'], true);
}

function user_can_edit_assignment(array $assignment): bool
{
    // SC Admin and SuperAdmin can edit any assignment
    if (user_can_create_assignment()) {
        return true;
    }
    // Committee Admin can edit assignments belonging to their committee
    return user_is_committee_admin_for((int) $assignment['assigned_committee_id']);
}

function user_is_committee_admin_for(int $committeeId): bool
{
    $user = current_user();
    if (!$user) {
        return false;
    }

    // Superadmin and SC Admin have full access everywhere
    if (in_array($user['role_level'], ['superadmin', 'sc_admin'], true)) {
        return true;
    }

    // Committee-specific admin roles: check if the role maps to this committee
    $roleToCode = [
        'cc_admin'   => 'CC',
        'fc_admin'   => 'FC',
        'ardc_admin' => 'ARDC',
    ];
    if (isset($roleToCode[$user['role_level']])) {
        $stmt = db()->prepare('SELECT short_code FROM committees WHERE id = ? LIMIT 1');
        $stmt->execute([$committeeId]);
        $code = $stmt->fetchColumn();
        if ($code === $roleToCode[$user['role_level']]) return true;
    }

    // Legacy committee_admin role or membership-flag-based admin
    $stmt = db()->prepare('SELECT 1 FROM user_committee_memberships WHERE user_id = ? AND committee_id = ? AND is_committee_admin = 1 LIMIT 1');
    $stmt->execute([(int) $user['id'], $committeeId]);
    return (bool) $stmt->fetchColumn();
}

function user_can_manage_supporting_members(array $assignment): bool
{
    $user = current_user();
    if (!$user) {
        return false;
    }

    return user_is_committee_admin_for((int) $assignment['assigned_committee_id']);
}

function user_can_manage_subtasks(array $assignment): bool
{
    $user = current_user();
    if (!$user) {
        return false;
    }

    return user_is_committee_admin_for((int) $assignment['assigned_committee_id']);
}

function user_can_update_subtask(array $assignment, array $subtask): bool
{
    $user = current_user();
    if (!$user) {
        return false;
    }

    if (user_can_manage_subtasks($assignment)) {
        return true;
    }

    return isset($subtask['assigned_user_id']) && (int) $subtask['assigned_user_id'] === (int) $user['id'];
}

function user_display_name(): string
{
    $user = current_user();
    if (!$user) {
        return 'Guest';
    }
    return $user['display_name'] ?: trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: $user['username'];
}

function activity_log(?int $userId, string $actionType, string $targetType, ?int $targetId, string $summary): void
{
    try {
        $stmt = db()->prepare('INSERT INTO activity_log (user_id, action_type, target_type, target_id, action_summary, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
        $stmt->execute([$userId, $actionType, $targetType, $targetId, $summary]);
    } catch (Throwable) {
        // Keep the starter lightweight: logging failure should not break the app.
    }
}