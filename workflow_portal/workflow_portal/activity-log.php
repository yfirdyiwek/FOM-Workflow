<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();
require_once __DIR__ . '/includes/layout.php';

$entries = db()->query('SELECT al.*, u.display_name FROM activity_log al LEFT JOIN users u ON u.id = al.user_id ORDER BY al.created_at DESC LIMIT 50')->fetchAll();
render_header('Activity Log', 'Visible proof that the starter app is saving and logging real actions.', 'activity', null);
?>
<article class="card section">
  <div class="section-header"><div><h2 class="section-title">Recent activity</h2><p class="section-sub">Login and assignment-creation events appear here immediately.</p></div></div>
  <div class="history-list">
    <?php foreach ($entries as $entry): ?>
      <div class="history-item">
        <div><strong><?= e($entry['action_summary']) ?></strong><p><?= e($entry['display_name'] ?: 'System') ?> · <?= e($entry['action_type']) ?></p></div>
        <div class="history-time"><?= e((new DateTime($entry['created_at']))->format('M j, Y g:i A')) ?></div>
      </div>
    <?php endforeach; ?>
    <?php if (!$entries): ?>
      <div class="history-item"><div><strong>No activity yet</strong><p>Log in and create an assignment to begin generating entries.</p></div><div class="history-time">—</div></div>
    <?php endif; ?>
  </div>
</article>
<?php render_footer(); ?>
