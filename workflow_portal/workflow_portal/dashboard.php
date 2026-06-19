<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();
require_once __DIR__ . '/includes/layout.php';

$user = current_user();
$pdo = db();

$where = 'WHERE a.work_type = "official"';
$params = [];
if (!user_is_sc_member()) {
    // Non-SC members see only assignments where they are lead or support person
    $uid = (int) $user['id'];
    $where .= ' AND (a.lead_user_id = ? OR a.id IN (
        SELECT assignment_id FROM assignment_supporting_members WHERE user_id = ?
    ))';
    $params = [$uid, $uid];
}

$sort = $_GET['sort'] ?? 'committee';
$dir = strtolower($_GET['dir'] ?? 'asc');
$dir = $dir === 'asc' ? 'asc' : 'desc';

$orderMap = [
    'seq'       => 'CASE WHEN a.sort_order IS NOT NULL THEN 0 ELSE 1 END, a.sort_order, a.title',
    // Default committee sort uses the same natural order as the left navigation: SC, CC, ARDC, FC.
    'committee' => 'CASE c.short_code WHEN "SC" THEN 1 WHEN "CC" THEN 2 WHEN "ARDC" THEN 3 WHEN "FC" THEN 4 ELSE 99 END',
    'title'     => 'a.title',
    'lead'      => 'u.display_name',
    'support'   => 'support_count',
    'subtasks'  => 'total_subtasks',
    'priority'  => 'FIELD(a.priority, "urgent", "high", "medium", "low")',
    'status'    => 'a.status',
    'due_date'  => 'a.due_date',
    'updated'   => 'a.updated_at',
];
if (!isset($orderMap[$sort])) {
    $sort = 'updated';
}
$orderExpr = $orderMap[$sort];
$orderDir = $sort === 'priority' ? ($dir === 'asc' ? 'DESC' : 'ASC') : strtoupper($dir);
$secondaryOrder = '';
if ($sort === 'committee') {
    $secondaryOrder = ', c.short_code ' . strtoupper($dir) . ', CASE WHEN a.sort_order IS NOT NULL THEN 0 ELSE 1 END ASC, a.sort_order ASC, a.title ASC';
}

$committees = [];
$memberships = [];
if (user_can_create_assignment()) {
    $committees = $pdo->query('SELECT id, name, short_code FROM committees WHERE is_active = 1 ORDER BY name')->fetchAll();
    $memberships = $pdo->query('SELECT u.id, u.display_name, u.first_name, u.last_name, m.committee_id, c.short_code
        FROM users u
        JOIN user_committee_memberships m ON m.user_id = u.id
        JOIN committees c ON c.id = m.committee_id
        WHERE u.is_active = 1 AND u.role_level != "superadmin"
        ORDER BY u.display_name, u.last_name, u.first_name')->fetchAll();
}

$createErrors = [];
$createValues = [
    'title' => '',
    'assigned_committee_id' => '',
    'lead_user_id' => '',
    'priority' => 'medium',
    'status' => 'assigned',
    'date_assigned' => date('Y-m-d'),
    'due_date' => '',
    'short_description' => '',
    'full_description' => '',
    'origin_source' => '',
];
$showCreateModal = false;

if (is_post() && (($_POST['form_action'] ?? '') === 'create_assignment_modal')) {
    if (!user_can_create_assignment()) {
        http_response_code(403);
        exit('Only the SuperAdmin or SC Admin can create official assignments in this starter.');
    }

    verify_csrf();
    $showCreateModal = true;

    foreach ($createValues as $key => $default) {
        $createValues[$key] = trim((string) ($_POST[$key] ?? $default));
    }

    if ($createValues['title'] === '') $createErrors[] = 'Title is required.';
    if ($createValues['assigned_committee_id'] === '') $createErrors[] = 'Assigned committee is required.';
    if ($createValues['lead_user_id'] === '') $createErrors[] = 'Lead person is required.';
    if ($createValues['date_assigned'] === '') $createErrors[] = 'Date assigned is required.';
    if ($createValues['due_date'] === '') $createErrors[] = 'Due date is required.';

    $eligibleLead = null;
    foreach ($memberships as $membership) {
        if ((string) $membership['committee_id'] === $createValues['assigned_committee_id'] && (string) $membership['id'] === $createValues['lead_user_id']) {
            $eligibleLead = $membership;
            break;
        }
    }
    if (!$eligibleLead) {
        $createErrors[] = 'Lead person must belong to the selected committee.';
    }

    if (!$createErrors) {
        $stmt = $pdo->prepare('INSERT INTO assignments (title, short_description, full_description, assigned_committee_id, lead_user_id, priority, status, origin_source, work_type, date_assigned, due_date, created_by_user_id, updated_by_user_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, "official", ?, ?, ?, ?, NOW(), NOW())');
        $stmt->execute([
            $createValues['title'],
            $createValues['short_description'],
            $createValues['full_description'],
            (int) $createValues['assigned_committee_id'],
            (int) $createValues['lead_user_id'],
            $createValues['priority'],
            $createValues['status'],
            $createValues['origin_source'],
            $createValues['date_assigned'],
            $createValues['due_date'],
            (int) current_user()['id'],
            (int) current_user()['id'],
        ]);
        $assignmentId = (int) $pdo->lastInsertId();
        activity_log((int) current_user()['id'], 'assignment_created', 'assignment', $assignmentId, 'Official assignment created: ' . $createValues['title']);
        flash('success', 'Assignment created: ' . $createValues['title']);
        $query = http_build_query(['sort' => $sort, 'dir' => $dir]);
        redirect('dashboard.php' . ($query ? '?' . $query : ''));
    }
}

function dashboard_count(PDO $pdo, string $where, array $params, string $extra): int {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM assignments a $where $extra");
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

$activeCount = dashboard_count($pdo, $where, $params, ' AND a.status NOT IN ("completed","archived")');
$dueThisWeek = dashboard_count($pdo, $where, $params, ' AND a.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND a.status NOT IN ("completed","archived")');
$overdueCount = dashboard_count($pdo, $where, $params, ' AND a.due_date < CURDATE() AND a.status NOT IN ("completed","archived")');
$reviewCount = dashboard_count($pdo, $where, $params, ' AND a.status = "ready_for_review"');

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
    ORDER BY $orderExpr $orderDir$secondaryOrder, a.updated_at DESC, a.id DESC
    LIMIT 12");
$stmt->execute($params);
$assignments = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT al.*, u.display_name
    FROM activity_log al
    LEFT JOIN users u ON u.id = al.user_id
    ORDER BY al.created_at DESC
    LIMIT 12");
$stmt->execute();
$activity = $stmt->fetchAll();

function dashboard_sort_link(string $key, string $label, string $currentSort, string $currentDir): string
{
    $nextDir = ($currentSort === $key && strtolower($currentDir) === 'asc') ? 'desc' : 'asc';
    $arrow = '';
    if ($currentSort === $key) {
        $arrow = strtolower($currentDir) === 'asc' ? ' ↑' : ' ↓';
    }
    $query = http_build_query(['sort' => $key, 'dir' => $nextDir]);
    return '<a class="sort-link" href="dashboard.php?' . e($query) . '">' . e($label) . $arrow . '</a>';
}

render_header('Home Dashboard', 'Organization-wide overview across SC, CC, ARDC, and FC.', 'home', null);
?>
<section>
  <article class="card section">
    <div style="display:grid;grid-template-columns:1fr auto 1fr;align-items:center;gap:12px;margin-bottom:16px;">
      <div>
        <button class="btn btn-secondary" type="button" data-modal-open="site-status-modal"
                style="min-height:34px;padding:7px 14px;font-size:.88rem;">Site Status</button>
      </div>
      <h2 class="section-title" style="font-size:1.45rem;margin:0;text-align:center;white-space:nowrap;">Assignments</h2>
      <div style="display:flex;justify-content:flex-end;">
        <?php if (user_can_create_assignment()): ?>
          <button class="btn btn-primary" type="button" data-modal-open="new-assignment-modal">Create Assignment</button>
        <?php endif; ?>
      </div>
    </div>

    <div class="table-wrap dashboard-table-wrap">
      <table class="dashboard-assignment-table">
        <thead>
          <tr>
            <th><?= dashboard_sort_link('seq', '#', $sort, $dir) ?></th>
            <th><?= dashboard_sort_link('committee', 'Committee', $sort, $dir) ?></th>
            <th><?= dashboard_sort_link('title', 'Assignment', $sort, $dir) ?></th>
            <th><?= dashboard_sort_link('lead', 'Lead', $sort, $dir) ?></th>
            <th><?= dashboard_sort_link('support', 'Support', $sort, $dir) ?></th>
            <th><?= dashboard_sort_link('subtasks', 'Subtasks', $sort, $dir) ?></th>
            <th><?= dashboard_sort_link('priority', 'Priority', $sort, $dir) ?></th>
            <th><?= dashboard_sort_link('status', 'Status', $sort, $dir) ?></th>
            <th><?= dashboard_sort_link('due_date', 'Due date', $sort, $dir) ?></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($assignments as $assignment): ?>
          <?php $rowClass = 'committee-row committee-row--' . strtolower((string) $assignment['short_code']); ?>
          <tr class="<?= e($rowClass) ?> row-clickable" data-href="assignment-detail.php?id=<?= e((string) $assignment['id']) ?>">
            <td style="text-align:center;color:#6c757d;font-size:.8rem;font-weight:700;"><?= $assignment['sort_order'] !== null ? e((string)(int)$assignment['sort_order']) : '' ?></td>
            <td>
              <span class="badge committee-badge <?= e(committee_badge_class($assignment['short_code'])) ?>"><?= e($assignment['short_code']) ?></span>
            </td>
            <td>
              <div class="assignment-title"><?= e($assignment['title']) ?></div>
              <div class="assignment-meta"><?= e($assignment['short_description'] ?: 'No short description yet.') ?></div>
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
          <tr><td colspan="9"><div class="assignment-meta">No assignments found yet.</div></td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </article>
</section>

<section>
  <article class="card section">
    <details id="activity-toggle">
      <summary style="list-style:none;display:grid;grid-template-columns:1fr auto 1fr;align-items:center;gap:12px;cursor:pointer;padding:0;">
        <span></span>
        <h2 class="section-title" style="font-size:1.45rem;margin:0;text-align:center;white-space:nowrap;">Recent Activities</h2>
        <span style="display:flex;justify-content:flex-end;">
          <span class="btn btn-secondary activity-toggle-label" style="min-height:34px;padding:7px 14px;font-size:.88rem;pointer-events:none;">Show Activity Log</span>
        </span>
      </summary>

      <div class="panel-list compact-panel-list" style="margin-top:16px;">
        <?php foreach ($activity as $entry): ?>
        <div class="panel-item">
          <strong><?= e($entry['action_summary']) ?></strong>
          <p><?= e($entry['display_name'] ?: 'System') ?> · <?= e((new DateTime($entry['created_at']))->format('M j, Y g:i A')) ?></p>
        </div>
        <?php endforeach; ?>
        <?php if (!$activity): ?>
        <div class="panel-item"><strong>No activity yet</strong><p>Once you create or update assignments, actions will appear here.</p></div>
        <?php endif; ?>
      </div>
    </details>
  </article>
</section>

<style>
#activity-toggle summary::-webkit-details-marker{display:none}
#activity-toggle[open] .activity-toggle-label{content:''}
</style>
<script>
(function(){
  const toggle = document.getElementById('activity-toggle');
  if (!toggle) return;
  const label = toggle.querySelector('.activity-toggle-label');
  function syncLabel(){
    label.textContent = toggle.open ? 'Hide Activity Log' : 'Show Activity Log';
  }
  toggle.addEventListener('toggle', syncLabel);
  syncLabel();
})();
</script>

<?php if (user_can_create_assignment()): ?>
<div class="modal-backdrop" id="site-status-modal" aria-hidden="true">
  <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="site-status-title">
    <div class="modal-card__header">
      <div>
        <h3 id="site-status-title">Site Status</h3>
        <p>Current counts for assignments visible to your role.</p>
      </div>
      <button class="icon-btn" type="button" data-modal-close aria-label="Close">✕</button>
    </div>
    <div class="modal-card__body">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
        <div style="background:var(--surface-card);border:1px solid var(--border-subtle);border-radius:14px;padding:16px 18px;">
          <div style="font-size:.82rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--text-secondary);margin-bottom:8px;">Active</div>
          <div style="font-size:2rem;font-weight:800;line-height:1;margin-bottom:4px;"><?= e((string) $activeCount) ?></div>
          <div style="font-size:.84rem;color:var(--text-secondary);">Official assignments not yet completed or archived.</div>
        </div>
        <div style="background:var(--surface-card);border:1px solid var(--border-subtle);border-radius:14px;padding:16px 18px;border-left:4px solid #b45309;">
          <div style="font-size:.82rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--text-secondary);margin-bottom:8px;">Due this week</div>
          <div style="font-size:2rem;font-weight:800;line-height:1;margin-bottom:4px;color:#b45309;"><?= e((string) $dueThisWeek) ?></div>
          <div style="font-size:.84rem;color:var(--text-secondary);">Due in the next 7 days.</div>
        </div>
        <div style="background:var(--surface-card);border:1px solid var(--border-subtle);border-radius:14px;padding:16px 18px;border-left:4px solid #b91c1c;">
          <div style="font-size:.82rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--text-secondary);margin-bottom:8px;">Overdue</div>
          <div style="font-size:2rem;font-weight:800;line-height:1;margin-bottom:4px;color:#b91c1c;"><?= e((string) $overdueCount) ?></div>
          <div style="font-size:.84rem;color:var(--text-secondary);">Past due date and not yet completed.</div>
        </div>
        <div style="background:var(--surface-card);border:1px solid var(--border-subtle);border-radius:14px;padding:16px 18px;border-left:4px solid #6d28d9;">
          <div style="font-size:.82rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--text-secondary);margin-bottom:8px;">Ready for review</div>
          <div style="font-size:2rem;font-weight:800;line-height:1;margin-bottom:4px;color:#6d28d9;"><?= e((string) $reviewCount) ?></div>
          <div style="font-size:.84rem;color:var(--text-secondary);">Awaiting committee or steering review.</div>
        </div>
      </div>
    </div>
    <div class="modal-card__footer">
      <button class="btn btn-secondary" type="button" data-modal-close>Close</button>
    </div>
  </div>
</div>

<div class="modal-backdrop<?= $showCreateModal ? ' is-open' : '' ?>" id="new-assignment-modal" aria-hidden="<?= $showCreateModal ? 'false' : 'true' ?>">
  <div class="modal-card modal-card--wide" role="dialog" aria-modal="true" aria-labelledby="new-assignment-title">
    <div class="modal-card__header">
      <div>
        <h3 id="new-assignment-title">New official assignment</h3>
        <p>Use the same core fields as the full-page form, but create it directly from Home Dashboard.</p>
      </div>
      <button class="icon-btn" type="button" data-modal-close aria-label="Close new assignment">✕</button>
    </div>
    <div class="modal-card__body">
      <?php if ($createErrors): ?>
        <div class="alert-card returned" style="margin-bottom:16px;"><strong>Please fix the following</strong><p><?= e(implode(' ', $createErrors)) ?></p></div>
      <?php endif; ?>

      <form method="post" class="form-demo" id="dashboard-assignment-create-form">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"/>
        <input type="hidden" name="form_action" value="create_assignment_modal"/>

        <div class="field"><label for="modal_title">Title</label><input id="modal_title" name="title" value="<?= e($createValues['title']) ?>" required/></div>

        <div class="layout-two-equal" style="gap:16px;">
          <div class="field">
            <label for="modal_assigned_committee_id">Assigned committee</label>
            <select id="modal_assigned_committee_id" name="assigned_committee_id" required data-committee-select>
              <option value="">Select committee…</option>
              <?php foreach ($committees as $committee): ?>
                <option value="<?= e((string) $committee['id']) ?>" <?= $createValues['assigned_committee_id'] === (string) $committee['id'] ? 'selected' : '' ?>><?= e($committee['name']) ?> (<?= e($committee['short_code']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label for="modal_lead_user_id">Lead person</label>
            <select id="modal_lead_user_id" name="lead_user_id" required data-lead-select>
              <option value="">Select lead…</option>
              <?php foreach ($memberships as $membership): ?>
                <option value="<?= e((string) $membership['id']) ?>" data-committee-id="<?= e((string) $membership['committee_id']) ?>" <?= $createValues['lead_user_id'] === (string) $membership['id'] ? 'selected' : '' ?>><?= e($membership['display_name'] ?: trim($membership['first_name'] . ' ' . $membership['last_name'])) ?> — <?= e($membership['short_code']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="layout-two-equal" style="gap:16px;">
          <div class="field"><label for="modal_priority">Priority</label><select id="modal_priority" name="priority"><option value="low" <?= $createValues['priority']==='low'?'selected':'' ?>>Low</option><option value="medium" <?= $createValues['priority']==='medium'?'selected':'' ?>>Medium</option><option value="high" <?= $createValues['priority']==='high'?'selected':'' ?>>High</option><option value="urgent" <?= $createValues['priority']==='urgent'?'selected':'' ?>>Urgent</option></select></div>
          <div class="field"><label for="modal_status">Initial status</label><select id="modal_status" name="status"><option value="assigned" <?= $createValues['status']==='assigned'?'selected':'' ?>>Assigned</option><option value="in_progress" <?= $createValues['status']==='in_progress'?'selected':'' ?>>In Progress</option><option value="ready_for_review" <?= $createValues['status']==='ready_for_review'?'selected':'' ?>>Ready for Review</option></select></div>
        </div>

        <div class="layout-two-equal" style="gap:16px;">
          <div class="field"><label for="modal_date_assigned">Date assigned</label><input id="modal_date_assigned" type="date" name="date_assigned" value="<?= e($createValues['date_assigned']) ?>" required/></div>
          <div class="field"><label for="modal_due_date">Due date</label><input id="modal_due_date" type="date" name="due_date" value="<?= e($createValues['due_date']) ?>" required/></div>
        </div>

        <div class="field"><label for="modal_short_description">Short description</label><input id="modal_short_description" name="short_description" value="<?= e($createValues['short_description']) ?>" placeholder="One-line summary for dashboard tables"/></div>
        <div class="field"><label for="modal_full_description">Full description / notes</label><textarea id="modal_full_description" name="full_description"><?= e($createValues['full_description']) ?></textarea></div>
        <div class="field"><label for="modal_origin_source">Notes</label><input id="modal_origin_source" name="origin_source" value="<?= e($createValues['origin_source']) ?>" placeholder="Optional notes"/></div>
      </form>
    </div>
    <div class="modal-card__footer">
      <button class="btn btn-secondary" type="button" data-modal-close>Cancel</button>
      <button class="btn btn-primary" type="submit" form="dashboard-assignment-create-form">Create Assignment</button>
    </div>
  </div>
</div>
<?php endif; ?>

<?php render_footer(); ?>