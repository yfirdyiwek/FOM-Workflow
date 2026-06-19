<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();
require_once __DIR__ . '/includes/layout.php';

if (!user_can_create_assignment()) {
    http_response_code(403);
    exit('Only the SuperAdmin or SC Admin can create official assignments in this starter.');
}

$pdo = db();
$committees = $pdo->query('SELECT id, name, short_code FROM committees WHERE is_active = 1 ORDER BY name')->fetchAll();
$memberships = $pdo->query('SELECT u.id, u.display_name, u.first_name, u.last_name, u.role_level, m.committee_id, c.short_code
    FROM users u
    JOIN user_committee_memberships m ON m.user_id = u.id
    JOIN committees c ON c.id = m.committee_id
    WHERE u.is_active = 1
    ORDER BY u.display_name, u.last_name, u.first_name')->fetchAll();

$errors = [];
$values = [
    'sort_order' => '',
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

if (is_post()) {
    verify_csrf();
    foreach ($values as $key => $default) {
        $values[$key] = trim((string) ($_POST[$key] ?? $default));
    }

    if ($values['title'] === '') $errors[] = 'Title is required.';
    if ($values['assigned_committee_id'] === '') $errors[] = 'Assigned committee is required.';
    if ($values['lead_user_id'] === '') $errors[] = 'Lead person is required.';
    if ($values['date_assigned'] === '') $errors[] = 'Date assigned is required.';
    if ($values['due_date'] === '') $errors[] = 'Due date is required.';

    $eligibleLead = null;
    foreach ($memberships as $membership) {
        if ((string) $membership['committee_id'] === $values['assigned_committee_id'] && (string) $membership['id'] === $values['lead_user_id']) {
            $eligibleLead = $membership;
            break;
        }
    }
    if (!$eligibleLead) {
        $errors[] = 'Lead person must belong to the selected committee.';
    }

    if (!$errors) {
        $sortOrder = ($values['sort_order'] !== '' && ctype_digit($values['sort_order'])) ? (int) $values['sort_order'] : null;
        $stmt = $pdo->prepare('INSERT INTO assignments (sort_order, title, short_description, full_description, assigned_committee_id, lead_user_id, priority, status, origin_source, work_type, date_assigned, due_date, created_by_user_id, updated_by_user_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, "official", ?, ?, ?, ?, NOW(), NOW())');
        $stmt->execute([
            $sortOrder,
            $values['title'],
            $values['short_description'],
            $values['full_description'],
            (int) $values['assigned_committee_id'],
            (int) $values['lead_user_id'],
            $values['priority'],
            $values['status'],
            $values['origin_source'],
            $values['date_assigned'],
            $values['due_date'],
            (int) current_user()['id'],
            (int) current_user()['id'],
        ]);
        $assignmentId = (int) $pdo->lastInsertId();
        activity_log((int) current_user()['id'], 'assignment_created', 'assignment', $assignmentId, 'Official assignment created: ' . $values['title']);
        flash('success', 'Assignment created.');
        redirect('assignment-detail.php?id=' . $assignmentId);
    }
}

render_header('Create Assignment', 'Start with the smallest working slice: official assignment, committee, lead, dashboard, and detail view.', 'assignments', null);
?>
<article class="card section">
  <div class="section-header">
    <div>
      <h2 class="section-title">New official assignment</h2>
      <p class="section-sub">This starter keeps the first form focused on the essentials. Subtasks, supporting members, and handoffs can be layered on next.</p>
    </div>
  </div>

  <?php if ($errors): ?>
    <div class="alert-card returned" style="margin-bottom:16px;"><strong>Please fix the following</strong><p><?= e(implode(' ', $errors)) ?></p></div>
  <?php endif; ?>

  <form method="post" class="panel-list" id="assignment-create-form">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"/>

      <div class="field"><label for="sort_order">Order # <span style="font-weight:400;color:var(--text-muted,#888);">(optional)</span></label><input id="sort_order" name="sort_order" type="number" min="1" max="255" style="width:6rem;" value="<?= e($values['sort_order']) ?>" placeholder="e.g. 1"/><p class="field-hint" style="margin:4px 0 0;font-size:.8rem;color:var(--text-muted,#888);">Set to control display order. Leave blank to order by date.</p></div>
      <div class="field"><label for="title">Title</label><input id="title" name="title" value="<?= e($values['title']) ?>" required/></div>

    <div class="layout-two-equal" style="gap:16px;">
      <div class="field">
        <label for="assigned_committee_id">Assigned committee</label>
        <select id="assigned_committee_id" name="assigned_committee_id" required>
          <option value="">Select committee…</option>
          <?php foreach ($committees as $committee): ?>
            <option value="<?= e((string) $committee['id']) ?>" <?= $values['assigned_committee_id'] === (string) $committee['id'] ? 'selected' : '' ?>><?= e($committee['name']) ?> (<?= e($committee['short_code']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="lead_user_id">Lead person</label>
        <select id="lead_user_id" name="lead_user_id" required>
          <option value="">Select lead…</option>
          <?php foreach ($memberships as $membership): ?>
            <option value="<?= e((string) $membership['id']) ?>" data-committee-id="<?= e((string) $membership['committee_id']) ?>" <?= $values['lead_user_id'] === (string) $membership['id'] ? 'selected' : '' ?>><?= e($membership['display_name'] ?: trim($membership['first_name'] . ' ' . $membership['last_name'])) ?> — <?= e($membership['short_code']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="layout-two-equal" style="gap:16px;">
      <div class="field"><label for="priority">Priority</label><select id="priority" name="priority"><option value="low" <?= $values['priority']==='low'?'selected':'' ?>>Low</option><option value="medium" <?= $values['priority']==='medium'?'selected':'' ?>>Medium</option><option value="high" <?= $values['priority']==='high'?'selected':'' ?>>High</option><option value="urgent" <?= $values['priority']==='urgent'?'selected':'' ?>>Urgent</option></select></div>
      <div class="field"><label for="status">Initial status</label><select id="status" name="status"><option value="assigned" <?= $values['status']==='assigned'?'selected':'' ?>>Assigned</option><option value="in_progress" <?= $values['status']==='in_progress'?'selected':'' ?>>In Progress</option><option value="ready_for_review" <?= $values['status']==='ready_for_review'?'selected':'' ?>>Ready for Review</option></select></div>
    </div>

    <div class="layout-two-equal" style="gap:16px;">
      <div class="field"><label for="date_assigned">Date assigned</label><input id="date_assigned" type="date" name="date_assigned" value="<?= e($values['date_assigned']) ?>" required/></div>
      <div class="field"><label for="due_date">Due date</label><input id="due_date" type="date" name="due_date" value="<?= e($values['due_date']) ?>" required/></div>
    </div>

    <div class="field"><label for="short_description">Short description</label><input id="short_description" name="short_description" value="<?= e($values['short_description']) ?>" placeholder="One-line summary for dashboard tables"/></div>
    <div class="field"><label for="full_description">Full description / notes</label><textarea id="full_description" name="full_description"><?= e($values['full_description']) ?></textarea></div>
    <div class="field"><label for="origin_source">Notes</label><input id="origin_source" name="origin_source" value="<?= e($values['origin_source']) ?>" placeholder="Optional notes"/></div>

    <div class="button-row">
      <button class="btn btn-primary" type="submit">Create Assignment</button>
      <a class="btn btn-secondary" href="dashboard.php">Cancel</a>
    </div>
  </form>
</article>
<script>
  (function(){
    const committeeSelect = document.getElementById('assigned_committee_id');
    const leadSelect = document.getElementById('lead_user_id');
    if (!committeeSelect || !leadSelect) return;
    const options = Array.from(leadSelect.querySelectorAll('option[data-committee-id]'));

    function filterLeads(){
      const committeeId = committeeSelect.value;
      const current = leadSelect.value;
      let currentVisible = false;
      options.forEach(option => {
        const show = !committeeId || option.dataset.committeeId === committeeId;
        option.hidden = !show;
        if (show && option.value === current) currentVisible = true;
      });
      if (!currentVisible) leadSelect.value = '';
    }

    committeeSelect.addEventListener('change', filterLeads);
    filterLeads();
  })();
</script>
<?php render_footer(); ?>
