<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();
require_once __DIR__ . '/includes/layout.php';

$pdo = db();
$scCode = 'SC';
$stmt = $pdo->prepare('SELECT a.*, c.name AS committee_name, c.short_code, u.display_name AS lead_name, COALESCE(sm.support_count, 0) AS support_count
    FROM assignments a
    JOIN committees c ON c.id = a.assigned_committee_id
    LEFT JOIN users u ON u.id = a.lead_user_id
    LEFT JOIN (SELECT assignment_id, COUNT(*) AS support_count FROM assignment_supporting_members GROUP BY assignment_id) sm ON sm.assignment_id = a.id
    WHERE c.short_code = ? AND a.work_type = "official"
    ORDER BY a.updated_at DESC, a.due_date ASC');
$stmt->execute([$scCode]);
$assignments = $stmt->fetchAll();

render_header('Steering Committee Dashboard', 'SC-owned assignments only. This does not duplicate the organization-wide Home dashboard.', 'sc', user_can_create_assignment() ? ['label' => 'Create Assignment', 'href' => 'assignment-create.php'] : null);
?>
<article class="card section">
  <div class="section-header">
    <div>
      <h2 class="section-title">SC-owned assignments</h2>
      <p class="section-sub">Assignments here belong officially to Steering Committee.</p>
    </div>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Title</th><th>Lead</th><th>Support</th><th>Status</th><th>Due Date</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($assignments as $assignment): ?>
        <tr class="committee-row committee-row--sc row-clickable" data-href="assignment-detail.php?id=<?= e((string) $assignment['id']) ?>">
          <td><div class="assignment-title"><?= e($assignment['title']) ?></div><div class="assignment-meta"><?= e($assignment['short_description'] ?: 'No short description yet.') ?></div></td>
          <td><?= e($assignment['lead_name'] ?: 'Unassigned') ?></td>
          <td><?= e((string) $assignment['support_count']) ?></td>
          <td><span class="badge <?= e(status_badge_class($assignment['status'])) ?>"><?= e(status_label($assignment['status'])) ?></span></td>
          <td><?= e(format_date($assignment['due_date'])) ?></td>
          <td><div class="row-actions"><a class="mini-btn" href="assignment-detail.php?id=<?= e((string) $assignment['id']) ?>">Open</a></div></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$assignments): ?>
        <tr><td colspan="6"><div class="assignment-meta">No SC-owned assignments yet.</div></td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</article>
<?php render_footer(); ?>
