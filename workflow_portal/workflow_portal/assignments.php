<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();
require_once __DIR__ . '/includes/layout.php';

$pdo  = db();
$user = current_user();
$canSeeAll = user_is_sc_member(); // superadmin, sc_admin, sc_member

$where  = 'WHERE a.work_type = "official"';
$params = [];

// --- Member filter (sc_member / sc_admin / superadmin only) ---
$filterUserId = null;
if ($canSeeAll && !empty($_GET['member']) && (int) $_GET['member'] > 0) {
    $filterUserId = (int) $_GET['member'];
    $where .= ' AND (a.lead_user_id = ? OR EXISTS (
        SELECT 1 FROM assignment_supporting_members sm
        WHERE sm.assignment_id = a.id AND sm.user_id = ?
    ))';
    $params[] = $filterUserId;
    $params[] = $filterUserId;
}

// --- Committee visibility for non-SC roles ---
if (!$canSeeAll) {
    $committeeIds = user_committee_ids((int) $user['id']);
    if (!$committeeIds) $committeeIds = [0];
    $placeholders = implode(',', array_fill(0, count($committeeIds), '?'));
    $where  .= " AND a.assigned_committee_id IN ($placeholders)";
    $params  = array_merge($params, $committeeIds);
}

$sortDir       = (isset($_GET['dir']) && $_GET['dir'] === 'desc') ? 'DESC' : 'ASC';
$sortDirToggle = $sortDir === 'ASC' ? 'desc' : 'asc';
$sortArrow     = $sortDir === 'ASC' ? '↑' : '↓';

$stmt = $pdo->prepare("SELECT a.*, c.name AS committee_name, c.short_code, u.display_name AS lead_name,
        COALESCE(sm.support_count, 0) AS support_count,
        COALESCE(st.total_subtasks, 0) AS total_subtasks,
        COALESCE(st.completed_subtasks, 0) AS completed_subtasks
    FROM assignments a
    JOIN committees c ON c.id = a.assigned_committee_id
    LEFT JOIN users u ON u.id = a.lead_user_id
    LEFT JOIN (SELECT assignment_id, COUNT(*) AS support_count FROM assignment_supporting_members GROUP BY assignment_id) sm ON sm.assignment_id = a.id
    LEFT JOIN (
        SELECT assignment_id,
               COUNT(*) AS total_subtasks,
               SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_subtasks
        FROM assignment_subtasks
        GROUP BY assignment_id
    ) st ON st.assignment_id = a.id
    $where
    ORDER BY CASE WHEN a.sort_order IS NOT NULL THEN 0 ELSE 1 END ASC,
             a.sort_order $sortDir,
             a.title $sortDir");
$stmt->execute($params);
$assignments = $stmt->fetchAll();

// Load members list for filter dropdown (sc_member+ only)
$members = [];
if ($canSeeAll) {
    $members = $pdo->query(
        'SELECT id, display_name FROM users WHERE is_active = 1 ORDER BY display_name ASC'
    )->fetchAll();
}

render_header('Assignments', '', 'assignments', null);
?>
<article class="card section">
  <div style="display:grid;grid-template-columns:1fr auto 1fr;align-items:center;gap:12px;margin-bottom:16px;">
    <span></span>
    <h2 class="section-title" style="font-size:1.45rem;margin:0;text-align:center;white-space:nowrap;">Assignments</h2>
    <span style="display:flex;justify-content:flex-end;">
      <?php if (user_can_create_assignment()): ?>
        <button class="btn btn-primary" type="button" data-modal-open="new-assignment-modal">Create Assignment</button>
      <?php endif; ?>
    </span>
  </div>

  <?php if ($canSeeAll): ?>
  <form method="get" action="assignments.php" style="display:flex;align-items:center;gap:10px;margin-bottom:16px;flex-wrap:wrap;">
    <?php if (isset($_GET['dir'])): ?>
      <input type="hidden" name="dir" value="<?= e($_GET['dir']) ?>">
    <?php endif; ?>
    <label for="member-filter" style="font-size:.875rem;font-weight:600;white-space:nowrap;">Filter by member:</label>
    <select id="member-filter" name="member" style="font-size:.875rem;padding:5px 10px;border-radius:6px;border:1px solid var(--color-border,#d1d5db);background:var(--surface,#fff);color:var(--text-primary,#111);">
      <option value="">— All members —</option>
      <?php foreach ($members as $m): ?>
        <option value="<?= e((string) $m['id']) ?>" <?= $filterUserId === (int) $m['id'] ? 'selected' : '' ?>>
          <?= e($m['display_name']) ?>
        </option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-secondary" style="font-size:.875rem;">Apply</button>
    <?php if ($filterUserId): ?>
      <a href="assignments.php" class="btn btn-secondary" style="font-size:.875rem;">✕ Clear</a>
    <?php endif; ?>
  </form>
  <?php endif; ?>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Committee</th>
          <th>
            <a href="assignments.php?dir=<?= e($sortDirToggle) ?>" style="text-decoration:none;color:inherit;display:inline-flex;align-items:center;gap:4px;">
              Title <span style="font-size:.8rem;"><?= $sortArrow ?></span>
            </a>
          </th>
          <th>Lead</th><th>Support</th><th>Subtasks</th><th>Priority</th><th>Status</th><th>Due Date</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($assignments as $assignment): ?>
          <?php $rowClass = 'committee-row committee-row--' . strtolower((string) $assignment['short_code']); ?>
          <tr class="<?= e($rowClass) ?> row-clickable" data-href="assignment-detail.php?id=<?= e((string) $assignment['id']) ?>">
            <td><span class="badge committee-badge <?= e(committee_badge_class($assignment['short_code'])) ?>"><?= e($assignment['short_code']) ?></span></td>
            <td>
              <div class="assignment-title">
                <?php if ($assignment['sort_order'] !== null): ?>
                  <span style="display:inline-block;min-width:1.6em;padding:0 4px;margin-right:5px;font-size:.75rem;font-weight:700;color:#fff;background:#6c757d;border-radius:3px;text-align:center;vertical-align:middle;"><?= e((string)(int)$assignment['sort_order']) ?></span>
                <?php endif; ?>
                <?= e($assignment['title']) ?>
              </div>
              <div class="assignment-meta"><?= e($assignment['short_description'] ?: '') ?></div>
            </td>
            <td><?= e($assignment['lead_name'] ?: 'Unassigned') ?></td>
            <td><?= e((string) $assignment['support_count']) ?></td>
            <td><?= e((string) $assignment['completed_subtasks']) ?> / <?= e((string) $assignment['total_subtasks']) ?></td>
            <td><span class="priority <?= e($assignment['priority']) ?>"><?= e(priority_label($assignment['priority'])) ?></span></td>
            <td><span class="<?= e(status_badge_class($assignment['status'])) ?>"><?= e(status_label($assignment['status'])) ?></span></td>
            <td class="<?= $assignment['due_date'] && $assignment['due_date'] < date('Y-m-d') && !in_array($assignment['status'], ['completed','archived'], true) ? 'due overdue' : '' ?>"><?= e(format_date($assignment['due_date'])) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$assignments): ?>
          <tr><td colspan="8"><div class="assignment-meta">No assignments yet.</div></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</article>
<?php render_footer(); ?>
