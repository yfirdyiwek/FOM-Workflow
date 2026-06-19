<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();
require_once __DIR__ . '/includes/layout.php';

$code = strtoupper(trim($_GET['code'] ?? 'CC'));
$allowed = ['CC' => 'cc', 'ARDC' => 'ardc', 'FC' => 'fc'];
if (!isset($allowed[$code])) {
    http_response_code(404);
    exit('Committee not found.');
}

$pdo = db();
$stmt = $pdo->prepare('SELECT * FROM committees WHERE short_code = ? LIMIT 1');
$stmt->execute([$code]);
$committee = $stmt->fetch();
if (!$committee) {
    http_response_code(404);
    exit('Committee not found.');
}

if (!user_is_adminish()) {
    $visible = user_committee_ids((int) current_user()['id']);
    if (!in_array((int) $committee['id'], $visible, true)) {
        http_response_code(403);
        exit('You do not have access to this committee dashboard.');
    }
}

$stmt = $pdo->prepare('SELECT a.*, u.display_name AS lead_name, COALESCE(sm.support_count, 0) AS support_count
    FROM assignments a
    LEFT JOIN users u ON u.id = a.lead_user_id
    LEFT JOIN (SELECT assignment_id, COUNT(*) AS support_count FROM assignment_supporting_members GROUP BY assignment_id) sm ON sm.assignment_id = a.id
    WHERE a.assigned_committee_id = ? AND a.work_type = "official"
    ORDER BY a.updated_at DESC, a.due_date ASC');
$stmt->execute([(int) $committee['id']]);
$assignments = $stmt->fetchAll();

$rowClass = 'committee-row committee-row--' . strtolower($code);
$title = $committee['name'] . ' Dashboard';
$subtitle = 'Committee-specific view of official assignments for ' . $committee['name'] . '.';
render_header($title, $subtitle, $allowed[$code], ['label' => 'Create Assignment', 'href' => 'assignment-create.php']);
?>
<article class="card section">
  <div class="section-header">
    <div>
      <h2 class="section-title"><?= e($committee['name']) ?> assignments</h2>
      <p class="section-sub">This pass keeps the dashboard simple while making supporting-member staffing visible to the committee.</p>
    </div>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Title</th><th>Lead</th><th>Support</th><th>Status</th><th>Due Date</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($assignments as $assignment): ?>
        <tr class="<?= e($rowClass) ?> row-clickable" data-href="assignment-detail.php?id=<?= e((string) $assignment['id']) ?>">
          <td><div class="assignment-title"><?= e($assignment['title']) ?></div><div class="assignment-meta"><?= e($assignment['short_description'] ?: 'No short description yet.') ?></div></td>
          <td><?= e($assignment['lead_name'] ?: 'Unassigned') ?></td>
          <td><?= e((string) $assignment['support_count']) ?></td>
          <td><span class="badge <?= e(status_badge_class($assignment['status'])) ?>"><?= e(status_label($assignment['status'])) ?></span></td>
          <td><?= e(format_date($assignment['due_date'])) ?></td>
          <td><div class="row-actions"><a class="mini-btn" href="assignment-detail.php?id=<?= e((string) $assignment['id']) ?>">Open</a></div></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$assignments): ?>
        <tr><td colspan="6"><div class="assignment-meta">No assignments in this committee yet.</div></td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</article>
<?php render_footer(); ?>
